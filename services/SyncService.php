<?php

namespace app\services;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\db\ActiveRecord;
use yii\web\UnprocessableEntityHttpException;
use app\models\BbBullet;
use app\models\BbBulletEntry;
use app\models\BbLabel;
use app\models\BbProject;
use app\models\BbReminder;
use app\models\BbTask;
use app\models\BbTaskLabel;
use app\models\BbTemplate;
use app\models\BbTemplateBullet;

class SyncService
{
    public function pull(int $userId, ?string $since): array
    {
        $sinceValue = $this->normalizeSince($since);

        $changes = [
            'bullets' => $this->fetchRows(BbBullet::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
            'templates' => $this->fetchRows(
                BbTemplate::find()
                    ->where(['deleted_at' => null])
                    ->andWhere(['>', 'updated_at', $sinceValue])
                    ->andWhere(['or', ['owner_user_id' => $userId], ['is_system' => 1]])
            ),
            'template_bullets' => $this->fetchRows(
                BbTemplateBullet::find()->alias('tb')
                    ->innerJoin('{{%bb_template}} t', 't.id = tb.template_id')
                    ->where(['tb.deleted_at' => null, 't.deleted_at' => null])
                    ->andWhere(['>', 'tb.updated_at', $sinceValue])
                    ->andWhere(['or', ['t.owner_user_id' => $userId], ['t.is_system' => 1]])
            ),
            'entries' => $this->fetchRows(BbBulletEntry::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
            'projects' => $this->fetchRows(BbProject::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
            'tasks' => $this->fetchRows(BbTask::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
            'labels' => $this->fetchRows(BbLabel::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
            'task_labels' => $this->fetchRows(
                BbTaskLabel::find()->alias('tl')
                    ->innerJoin('{{%bb_task}} t', 't.id = tl.task_id')
                    ->where(['tl.deleted_at' => null, 't.user_id' => $userId])
                    ->andWhere(['>', 'tl.updated_at', $sinceValue])
            ),
            'reminders' => $this->fetchRows(BbReminder::find()->where(['user_id' => $userId, 'deleted_at' => null])->andWhere(['>', 'updated_at', $sinceValue])),
        ];

        $deletions = [
            'bullets' => $this->fetchDeletedIds(BbBullet::find()->where(['user_id' => $userId]), $sinceValue),
            'templates' => $this->fetchDeletedIds(BbTemplate::find()->where(['or', ['owner_user_id' => $userId], ['is_system' => 1]]), $sinceValue),
            'template_bullets' => $this->fetchDeletedIds(
                BbTemplateBullet::find()->alias('tb')
                    ->innerJoin('{{%bb_template}} t', 't.id = tb.template_id')
                    ->where(['or', ['t.owner_user_id' => $userId], ['t.is_system' => 1]]),
                $sinceValue,
                'tb.id'
            ),
            'entries' => $this->fetchDeletedIds(BbBulletEntry::find()->where(['user_id' => $userId]), $sinceValue),
            'projects' => $this->fetchDeletedIds(BbProject::find()->where(['user_id' => $userId]), $sinceValue),
            'tasks' => $this->fetchDeletedIds(BbTask::find()->where(['user_id' => $userId]), $sinceValue),
            'labels' => $this->fetchDeletedIds(BbLabel::find()->where(['user_id' => $userId]), $sinceValue),
            'task_labels' => $this->fetchDeletedIds(
                BbTaskLabel::find()->alias('tl')
                    ->innerJoin('{{%bb_task}} t', 't.id = tl.task_id')
                    ->where(['t.user_id' => $userId]),
                $sinceValue,
                'tl.id'
            ),
            'reminders' => $this->fetchDeletedIds(BbReminder::find()->where(['user_id' => $userId]), $sinceValue),
        ];

        return [
            'server_time' => $this->nowUtc(),
            'since' => $sinceValue,
            'changes' => $changes,
            'deletions' => $deletions,
        ];
    }

    public function push(int $userId, array $changes, array $deletions, ?string $since = null): array
    {
        $applied = [
            'changes' => 0,
            'deletions' => 0,
        ];

        foreach (($changes['bullets'] ?? []) as $row) {
            $this->upsertUserScoped(BbBullet::class, $row, $userId, 'user_id');
            $applied['changes']++;
        }

        foreach (($changes['templates'] ?? []) as $row) {
            $row['is_system'] = 0;
            $this->upsertUserScoped(BbTemplate::class, $row, $userId, 'owner_user_id');
            $applied['changes']++;
        }

        foreach (($changes['template_bullets'] ?? []) as $row) {
            $this->upsertTemplateBullet($row, $userId);
            $applied['changes']++;
        }

        foreach (($changes['entries'] ?? []) as $row) {
            $this->upsertEntry($row, $userId);
            $applied['changes']++;
        }

        foreach (($changes['projects'] ?? []) as $row) {
            $this->upsertUserScoped(BbProject::class, $row, $userId, 'user_id');
            $applied['changes']++;
        }

        foreach (($changes['tasks'] ?? []) as $row) {
            $this->upsertUserScoped(BbTask::class, $row, $userId, 'user_id');
            $applied['changes']++;
        }

        foreach (($changes['labels'] ?? []) as $row) {
            $this->upsertUserScoped(BbLabel::class, $row, $userId, 'user_id');
            $applied['changes']++;
        }

        foreach (($changes['task_labels'] ?? []) as $row) {
            $this->upsertTaskLabel($row, $userId);
            $applied['changes']++;
        }

        foreach (($changes['reminders'] ?? []) as $row) {
            $this->upsertUserScoped(BbReminder::class, $row, $userId, 'user_id');
            $applied['changes']++;
        }

        foreach ($deletions as $entity => $ids) {
            foreach ((array) $ids as $id) {
                if ($this->softDeleteEntity($entity, (int) $id, $userId)) {
                    $applied['deletions']++;
                }
            }
        }

        $response = [
            'server_time' => $this->nowUtc(),
            'applied' => $applied,
        ];

        if ($since !== null && $since !== '') {
            $response['diff'] = $this->pull($userId, $since);
        }

        return $response;
    }

    private function upsertUserScoped(string $modelClass, array $data, int $userId, string $userColumn): ActiveRecord
    {
        /** @var ActiveRecord|null $model */
        $model = isset($data['id']) ? $modelClass::findOne((int) $data['id']) : null;

        if ($model === null) {
            $model = new $modelClass();
            if (isset($data['id']) && $model->hasAttribute('id')) {
                $model->setAttribute('id', (int) $data['id']);
            }
        }

        if (!$model->isNewRecord && (int) $model->getAttribute($userColumn) !== $userId) {
            throw new UnprocessableEntityHttpException('Cross-user sync update blocked.');
        }

        if ($model->hasAttribute($userColumn)) {
            $model->setAttribute($userColumn, $userId);
        }

        if ($this->isStaleUpdate($model, $data['updated_at'] ?? null)) {
            return $model;
        }

        foreach ($data as $attribute => $value) {
            if (in_array($attribute, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            if ($model->hasAttribute($attribute)) {
                $model->setAttribute($attribute, $value);
            }
        }

        if (!$model->save()) {
            throw new UnprocessableEntityHttpException('Sync upsert failed for ' . $modelClass . ': ' . json_encode($model->errors));
        }

        return $model;
    }

    private function upsertEntry(array $data, int $userId): void
    {
        $model = null;

        if (isset($data['id'])) {
            $model = BbBulletEntry::findOne((int) $data['id']);
        }

        if ($model === null && isset($data['bullet_id'], $data['entry_date'])) {
            $model = BbBulletEntry::find()
                ->where(['user_id' => $userId, 'bullet_id' => $data['bullet_id'], 'entry_date' => $data['entry_date']])
                ->one();
        }

        if ($model === null) {
            $model = new BbBulletEntry();
            if (isset($data['id']) && $model->hasAttribute('id')) {
                $model->setAttribute('id', (int) $data['id']);
            }
        }

        if (!$model->isNewRecord && (int) $model->user_id !== $userId) {
            throw new UnprocessableEntityHttpException('Cross-user entry sync update blocked.');
        }

        if ($this->isStaleUpdate($model, $data['updated_at'] ?? null)) {
            return;
        }

        $model->setAttributes($data, false);
        $model->user_id = $userId;
        $model->deleted_at = $data['deleted_at'] ?? null;

        if (!$model->save()) {
            throw new UnprocessableEntityHttpException('Sync upsert failed for entry: ' . json_encode($model->errors));
        }
    }

    private function upsertTemplateBullet(array $data, int $userId): void
    {
        $template = BbTemplate::findOne((int) ($data['template_id'] ?? 0));
        if ($template === null) {
            throw new UnprocessableEntityHttpException('Template not found for template_bullets sync.');
        }

        if ((int) $template->owner_user_id !== $userId && !(bool) $template->is_system) {
            throw new UnprocessableEntityHttpException('Template not accessible for sync.');
        }

        $model = null;
        if (isset($data['id'])) {
            $model = BbTemplateBullet::findOne((int) $data['id']);
        }

        if ($model === null && isset($data['template_id'], $data['bullet_id'])) {
            $model = BbTemplateBullet::find()
                ->where(['template_id' => $data['template_id'], 'bullet_id' => $data['bullet_id']])
                ->one();
        }

        if ($model === null) {
            $model = new BbTemplateBullet();
            if (isset($data['id']) && $model->hasAttribute('id')) {
                $model->setAttribute('id', (int) $data['id']);
            }
        }

        if ($this->isStaleUpdate($model, $data['updated_at'] ?? null)) {
            return;
        }

        $model->setAttributes($data, false);
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException('Sync upsert failed for template_bullet: ' . json_encode($model->errors));
        }
    }

    private function upsertTaskLabel(array $data, int $userId): void
    {
        $task = BbTask::findOne((int) ($data['task_id'] ?? 0));
        if ($task === null || (int) $task->user_id !== $userId) {
            throw new UnprocessableEntityHttpException('Task not accessible for task_label sync.');
        }

        $model = null;
        if (isset($data['id'])) {
            $model = BbTaskLabel::findOne((int) $data['id']);
        }

        if ($model === null && isset($data['task_id'], $data['label_id'])) {
            $model = BbTaskLabel::find()
                ->where(['task_id' => $data['task_id'], 'label_id' => $data['label_id']])
                ->one();
        }

        if ($model === null) {
            $model = new BbTaskLabel();
            if (isset($data['id']) && $model->hasAttribute('id')) {
                $model->setAttribute('id', (int) $data['id']);
            }
        }

        if ($this->isStaleUpdate($model, $data['updated_at'] ?? null)) {
            return;
        }

        $model->setAttributes($data, false);
        if (!$model->save()) {
            throw new UnprocessableEntityHttpException('Sync upsert failed for task_label: ' . json_encode($model->errors));
        }
    }

    private function softDeleteEntity(string $entity, int $id, int $userId): bool
    {
        switch ($entity) {
            case 'bullets':
                return $this->softDeleteUserModel(BbBullet::class, $id, $userId, 'user_id');
            case 'templates':
                return $this->softDeleteUserModel(BbTemplate::class, $id, $userId, 'owner_user_id');
            case 'template_bullets':
                $model = BbTemplateBullet::findOne($id);
                if ($model === null) {
                    return false;
                }
                $template = BbTemplate::findOne($model->template_id);
                if ($template === null || ((int) $template->owner_user_id !== $userId && !(bool) $template->is_system)) {
                    return false;
                }
                return $model->softDelete();
            case 'entries':
                return $this->softDeleteUserModel(BbBulletEntry::class, $id, $userId, 'user_id');
            case 'projects':
                return $this->softDeleteUserModel(BbProject::class, $id, $userId, 'user_id');
            case 'tasks':
                return $this->softDeleteUserModel(BbTask::class, $id, $userId, 'user_id');
            case 'labels':
                return $this->softDeleteUserModel(BbLabel::class, $id, $userId, 'user_id');
            case 'task_labels':
                $model = BbTaskLabel::findOne($id);
                if ($model === null) {
                    return false;
                }
                $task = BbTask::findOne($model->task_id);
                if ($task === null || (int) $task->user_id !== $userId) {
                    return false;
                }
                return $model->softDelete();
            case 'reminders':
                return $this->softDeleteUserModel(BbReminder::class, $id, $userId, 'user_id');
            default:
                return false;
        }
    }

    private function softDeleteUserModel(string $modelClass, int $id, int $userId, string $userColumn): bool
    {
        $model = $modelClass::findOne($id);
        if ($model === null) {
            return false;
        }

        if ((int) $model->getAttribute($userColumn) !== $userId) {
            return false;
        }

        return method_exists($model, 'softDelete') ? $model->softDelete() : false;
    }

    private function fetchRows($query): array
    {
        $models = $query->orderBy(['updated_at' => SORT_ASC, 'id' => SORT_ASC])->all();
        return array_map(static fn($model) => $model->toArray(), $models);
    }

    private function fetchDeletedIds($query, string $since, string $column = 'id'): array
    {
        return $query
            ->andWhere(['IS NOT', 'deleted_at', null])
            ->andWhere(['>', 'deleted_at', $since])
            ->select([$column])
            ->column();
    }

    private function normalizeSince(?string $since): string
    {
        if ($since === null || trim($since) === '') {
            return '1970-01-01 00:00:00.000000';
        }

        try {
            return (new DateTimeImmutable($since, new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        } catch (\Throwable $e) {
            return '1970-01-01 00:00:00.000000';
        }
    }

    private function isStaleUpdate(ActiveRecord $model, ?string $incomingUpdatedAt): bool
    {
        if ($model->isNewRecord || $incomingUpdatedAt === null || !$model->hasAttribute('updated_at')) {
            return false;
        }

        $current = (string) $model->getAttribute('updated_at');
        try {
            $incoming = new DateTimeImmutable($incomingUpdatedAt, new DateTimeZone('UTC'));
            $stored = new DateTimeImmutable($current, new DateTimeZone('UTC'));
            return $incoming <= $stored;
        } catch (\Throwable $e) {
            Yii::warning('Failed to compare updated_at in sync service: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
