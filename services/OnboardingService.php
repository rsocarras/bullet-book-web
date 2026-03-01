<?php

namespace app\services;

use RuntimeException;
use Yii;
use yii\db\Connection;
use yii\db\Exception;
use yii\web\NotFoundHttpException;
use app\models\BbBullet;
use app\models\BbTemplate;
use app\models\BbTemplateBullet;
use app\models\BbUserSetup;
use app\models\BbUserTemplate;

class OnboardingService
{
    /**
     * MVP rule: onboarding setup accepts only system templates.
     * TODO: switch to true when product enables user-owned templates on onboarding.
     */
    private const ALLOW_OWNED_TEMPLATES_IN_SETUP = false;

    public function __construct(private readonly ?Connection $db = null)
    {
    }

    /**
     * Executes onboarding setup in a transaction and guarantees idempotent cloning/activation.
     *
     * @throws NotFoundHttpException
     * @throws Exception
     */
    public function runSetup(int $userId, array $templateIds, ?string $timezone): array
    {
        $db = $this->db ?? Yii::$app->db;
        $templateIds = $this->normalizeTemplateIds($templateIds);
        $templates = $this->findAllowedTemplates($userId, $templateIds);

        if (count($templates) !== count($templateIds)) {
            // 404 avoids exposing whether an inaccessible template exists.
            throw new NotFoundHttpException('Template not found.');
        }

        $tx = $db->beginTransaction();
        try {
            $serverTime = BbBullet::nowUtc();
            $onboardedAt = $this->upsertUserSetup($userId, $timezone, $serverTime);
            $this->upsertUserTemplateSelection($userId, $templateIds);
            $activatedBulletsCount = $this->activateOrCloneBullets($userId, $templateIds);

            $tx->commit();

            return [
                'onboarded_at' => $onboardedAt,
                'selected_template_ids' => $templateIds,
                'activated_bullets_count' => $activatedBulletsCount,
                'server_time' => $serverTime,
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Onboarding setup failed: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    private function normalizeTemplateIds(array $templateIds): array
    {
        $normalized = [];
        foreach ($templateIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }

    private function findAllowedTemplates(int $userId, array $templateIds): array
    {
        $query = BbTemplate::find()
            ->where(['id' => $templateIds, 'deleted_at' => null]);

        if (self::ALLOW_OWNED_TEMPLATES_IN_SETUP) {
            $query->andWhere(['or', ['is_system' => 1], ['owner_user_id' => $userId]]);
        } else {
            $query->andWhere(['is_system' => 1]);
        }

        return $query->all();
    }

    private function upsertUserSetup(int $userId, ?string $timezone, string $now): string
    {
        if (!$this->tableExists('bb_user_setup')) {
            // TODO: remove fallback when bb_user_setup is guaranteed in all environments.
            Yii::warning('bb_user_setup table is missing. Using compatibility fallback.', __METHOD__);
            return $now;
        }

        $setup = BbUserSetup::findOne(['user_id' => $userId]);
        if ($setup === null) {
            $setup = new BbUserSetup();
            $setup->user_id = $userId;
            if ($setup->hasAttribute('onboarded_at')) {
                $setup->setAttribute('onboarded_at', $now);
            }
        }

        if ($timezone !== null && $timezone !== '' && $setup->hasAttribute('timezone')) {
            $setup->setAttribute('timezone', $timezone);
        }

        if ($setup->hasAttribute('onboarded_at') && empty($setup->getAttribute('onboarded_at'))) {
            $setup->setAttribute('onboarded_at', $now);
        }

        if (!$setup->save()) {
            throw new RuntimeException('Failed to persist onboarding setup state: ' . json_encode($setup->errors));
        }

        return (string) ($setup->hasAttribute('onboarded_at') ? $setup->getAttribute('onboarded_at') : $now);
    }

    private function upsertUserTemplateSelection(int $userId, array $templateIds): void
    {
        if (!$this->tableExists('bb_user_template')) {
            // TODO: remove fallback when bb_user_template is guaranteed in all environments.
            Yii::warning('bb_user_template table is missing. Using compatibility fallback.', __METHOD__);
            return;
        }

        foreach ($templateIds as $templateId) {
            $row = BbUserTemplate::findOne(['user_id' => $userId, 'template_id' => $templateId]);
            if ($row === null) {
                $row = new BbUserTemplate();
                $row->user_id = $userId;
                $row->template_id = (int) $templateId;
            }

            if (!$row->save()) {
                throw new RuntimeException('Failed to persist user template selection: ' . json_encode($row->errors));
            }
        }
    }

    private function activateOrCloneBullets(int $userId, array $templateIds): int
    {
        $hasSourceBulletId = $this->columnExists('bb_bullet', 'source_bullet_id');
        $hasBulletSortOrder = $this->columnExists('bb_bullet', 'sort_order');
        $hasBulletIsSystem = $this->columnExists('bb_bullet', 'is_system');
        $hasBulletIsActive = $this->columnExists('bb_bullet', 'is_active');

        $templateBullets = BbTemplateBullet::find()
            ->where(['template_id' => $templateIds, 'deleted_at' => null])
            ->orderBy(['template_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $affectedUserBulletIds = [];

        foreach ($templateBullets as $tb) {
            $sourceQuery = BbBullet::find()
                ->where(['id' => $tb->bullet_id, 'deleted_at' => null]);

            if ($hasBulletIsSystem) {
                $sourceQuery->andWhere(['is_system' => 1]);
            } else {
                // TODO: with no is_system column, template linkage is used as system source proxy.
            }

            $sourceBullet = $sourceQuery->one();
            if ($sourceBullet === null) {
                continue;
            }

            $userBullet = $this->findExistingUserBullet($userId, $sourceBullet, $hasSourceBulletId);

            if ($userBullet !== null) {
                if ($hasBulletIsActive) {
                    $userBullet->setAttribute('is_active', 1);
                }
                if ($hasSourceBulletId) {
                    $userBullet->setAttribute('source_bullet_id', (int) $sourceBullet->id);
                }
                if ($hasBulletSortOrder) {
                    $userBullet->setAttribute('sort_order', (int) $tb->sort_order);
                }

                if (!$userBullet->save()) {
                    throw new RuntimeException('Failed to activate existing bullet: ' . json_encode($userBullet->errors));
                }

                $affectedUserBulletIds[(int) $userBullet->id] = true;
                continue;
            }

            $newBullet = new BbBullet();
            $newBullet->user_id = $userId;

            foreach (['name', 'bullet_type', 'input_type', 'scale_min', 'scale_max', 'scale_labels', 'icon', 'color', 'weight'] as $attr) {
                if ($newBullet->hasAttribute($attr) && $sourceBullet->hasAttribute($attr)) {
                    $newBullet->setAttribute($attr, $sourceBullet->getAttribute($attr));
                }
            }

            if ($newBullet->hasAttribute('deleted_at')) {
                $newBullet->setAttribute('deleted_at', null);
            }
            if ($hasBulletIsActive) {
                $newBullet->setAttribute('is_active', 1);
            }
            if ($hasSourceBulletId) {
                $newBullet->setAttribute('source_bullet_id', (int) $sourceBullet->id);
            }
            if ($hasBulletSortOrder) {
                $newBullet->setAttribute('sort_order', (int) $tb->sort_order);
            } else {
                // TODO: when bb_bullet.sort_order is absent, order should be resolved from template relation at read time.
            }
            if ($hasBulletIsSystem) {
                $newBullet->setAttribute('is_system', 0);
            }

            if (!$newBullet->save()) {
                throw new RuntimeException('Failed to clone source bullet: ' . json_encode($newBullet->errors));
            }

            $affectedUserBulletIds[(int) $newBullet->id] = true;
        }

        return count($affectedUserBulletIds);
    }

    private function findExistingUserBullet(int $userId, BbBullet $sourceBullet, bool $hasSourceBulletId): ?BbBullet
    {
        $query = BbBullet::find()
            ->where(['user_id' => $userId, 'deleted_at' => null]);

        if ($hasSourceBulletId) {
            return $query->andWhere(['source_bullet_id' => (int) $sourceBullet->id])->one();
        }

        // Compatibility fallback when source_bullet_id does not exist.
        // TODO: replace with source_bullet_id once column is available in all environments.
        return $query
            ->andWhere([
                'name' => $sourceBullet->name,
                'bullet_type' => $sourceBullet->bullet_type,
                'input_type' => $sourceBullet->input_type,
            ])
            ->one();
    }

    private function tableExists(string $table): bool
    {
        $db = $this->db ?? Yii::$app->db;
        return $db->schema->getTableSchema('{{%' . $table . '}}', true) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $db = $this->db ?? Yii::$app->db;
        $schema = $db->schema->getTableSchema('{{%' . $table . '}}', true);
        return $schema !== null && isset($schema->columns[$column]);
    }
}
