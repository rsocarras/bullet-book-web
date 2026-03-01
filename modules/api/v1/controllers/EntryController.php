<?php

namespace app\modules\api\v1\controllers;

use DateTimeImmutable;
use DateTimeZone;
use app\models\BbBullet;
use app\models\BbBulletEntry;

class EntryController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'index' => ['GET'],
            'create' => ['POST'],
        ];
    }

    public function actionCreate(): array
    {
        $payload = $this->bodyParams();
        $bulletId = (int) ($payload['bullet_id'] ?? 0);
        $entryDate = (string) ($payload['entry_date'] ?? '');

        if ($bulletId <= 0 || $entryDate === '') {
            return $this->fail('bullet_id and entry_date are required.', [], 422);
        }

        $bullet = BbBullet::find()
            ->where(['id' => $bulletId, 'user_id' => $this->userId(), 'deleted_at' => null])
            ->one();

        if ($bullet === null) {
            return $this->fail('Bullet not found.', [], 404);
        }

        $entry = BbBulletEntry::find()
            ->where(['user_id' => $this->userId(), 'bullet_id' => $bulletId, 'entry_date' => $entryDate])
            ->one();

        $isNew = false;
        if ($entry === null) {
            $entry = new BbBulletEntry();
            $isNew = true;
        }

        $entry->setAttributes($payload, false);
        $entry->user_id = $this->userId();
        $entry->bullet_id = $bulletId;
        $entry->entry_date = $entryDate;
        $entry->deleted_at = null;

        $typeError = $this->validateEntryAgainstBullet($entry, $bullet);
        if ($typeError !== null) {
            return $this->fail($typeError, [], 422);
        }

        if (!$entry->save()) {
            return $this->fail('Validation failed.', $entry->errors, 422);
        }

        return $this->success($entry->toArray(), $isNew ? 201 : 200);
    }

    public function actionIndex(?string $from = null, ?string $to = null): array
    {
        if ($from === null || $to === null) {
            return $this->fail('from and to are required (YYYY-MM-DD).', [], 422);
        }

        try {
            $fromDate = new DateTimeImmutable($from, new DateTimeZone('UTC'));
            $toDate = new DateTimeImmutable($to, new DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return $this->fail('Invalid date range format. Use YYYY-MM-DD.', [], 422);
        }

        if ($fromDate > $toDate) {
            return $this->fail('from must be lower or equal than to.', [], 422);
        }

        if ($fromDate->diff($toDate)->days > 366) {
            return $this->fail('Date range cannot exceed 366 days.', [], 422);
        }

        $rows = BbBulletEntry::find()
            ->where(['user_id' => $this->userId(), 'deleted_at' => null])
            ->andWhere(['between', 'entry_date', $fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])
            ->orderBy(['entry_date' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->success(array_map(static fn(BbBulletEntry $row) => $row->toArray(), $rows));
    }

    private function validateEntryAgainstBullet(BbBulletEntry $entry, BbBullet $bullet): ?string
    {
        $present = 0;
        if ($entry->value_int !== null && $entry->value_int !== '') {
            $present++;
        }
        if ($entry->value_decimal !== null && $entry->value_decimal !== '') {
            $present++;
        }
        if ($entry->value_text !== null && $entry->value_text !== '') {
            $present++;
        }

        if ($present !== 1) {
            return 'Exactly one value field is required: value_int or value_decimal or value_text.';
        }

        switch ($bullet->input_type) {
            case BbBullet::INPUT_BINARY:
                if ($entry->value_int === null || !in_array((int) $entry->value_int, [0, 1], true)) {
                    return 'binary bullets require value_int as 0 or 1.';
                }
                break;

            case BbBullet::INPUT_SCALE:
            case BbBullet::INPUT_STARS:
                if ($entry->value_int === null) {
                    return 'scale/stars bullets require value_int.';
                }
                $value = (int) $entry->value_int;
                if ($bullet->scale_min !== null && $value < (int) $bullet->scale_min) {
                    return 'value_int is below scale_min.';
                }
                if ($bullet->scale_max !== null && $value > (int) $bullet->scale_max) {
                    return 'value_int is above scale_max.';
                }
                break;

            case BbBullet::INPUT_NUMERIC:
                if ($entry->value_decimal === null && $entry->value_int === null) {
                    return 'numeric bullets require value_decimal or value_int.';
                }
                break;

            case BbBullet::INPUT_TEXT:
                if ($entry->value_text === null || trim((string) $entry->value_text) === '') {
                    return 'text bullets require value_text.';
                }
                break;
        }

        return null;
    }
}
