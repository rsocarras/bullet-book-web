<?php

namespace app\modules\api\v1\controllers;

use DateTimeImmutable;
use DateTimeZone;
use app\models\BbBullet;
use app\models\BbBulletEntry;

class StatsController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'heatmap' => ['GET'],
        ];
    }

    public function actionHeatmap(int $bullet_id, string $month): array
    {
        if (!preg_match('/^\\d{4}-\\d{2}$/', $month)) {
            return $this->fail('month must have format YYYY-MM.', [], 422);
        }

        $bullet = BbBullet::find()
            ->where(['id' => $bullet_id, 'user_id' => $this->userId(), 'deleted_at' => null])
            ->one();

        if ($bullet === null) {
            return $this->fail('Bullet not found.', [], 404);
        }

        try {
            $start = new DateTimeImmutable($month . '-01', new DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return $this->fail('Invalid month value.', [], 422);
        }

        $end = $start->modify('last day of this month');

        $entries = BbBulletEntry::find()
            ->where([
                'user_id' => $this->userId(),
                'bullet_id' => $bullet_id,
                'deleted_at' => null,
            ])
            ->andWhere(['between', 'entry_date', $start->format('Y-m-d'), $end->format('Y-m-d')])
            ->all();

        $indexed = [];
        foreach ($entries as $entry) {
            $indexed[$entry->entry_date] = $entry;
        }

        $days = [];
        $cursor = $start;
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $entry = $indexed[$date] ?? null;

            $days[] = [
                'date' => $date,
                'value' => $entry === null ? null : $this->extractValue($entry),
                'has_entry' => $entry !== null,
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return $this->success([
            'bullet_id' => $bullet_id,
            'month' => $month,
            'days' => $days,
        ]);
    }

    private function extractValue(BbBulletEntry $entry)
    {
        if ($entry->value_decimal !== null) {
            return (float) $entry->value_decimal;
        }

        if ($entry->value_int !== null) {
            return (int) $entry->value_int;
        }

        if ($entry->value_text !== null) {
            return (string) $entry->value_text;
        }

        return null;
    }
}
