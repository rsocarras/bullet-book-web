<?php

namespace app\jobs;

use Yii;
use yii\base\BaseObject;
use yii\db\Query;
use yii\queue\JobInterface;
use app\models\BbReminder;

class WeeklySummaryJob extends BaseObject implements JobInterface
{
    public ?int $userId = null;

    public function execute($queue): void
    {
        $userIds = $this->resolveUsers();
        $now = gmdate('Y-m-d H:i:s.u');

        foreach ($userIds as $userId) {
            $reminder = new BbReminder();
            $reminder->user_id = (int) $userId;
            $reminder->kind = BbReminder::KIND_WEEKLY_SUMMARY;
            $reminder->channel = BbReminder::CHANNEL_PUSH;
            $reminder->fire_at = $now;
            $reminder->status = BbReminder::STATUS_QUEUED;
            $reminder->payload = [
                'title' => 'Weekly summary',
                'body' => 'Your Bullet Book weekly summary is ready.',
            ];

            if (!$reminder->save()) {
                Yii::warning('WeeklySummaryJob failed to create reminder for user ' . $userId, __METHOD__);
                continue;
            }

            if (Yii::$app->has('queue')) {
                Yii::$app->queue->push(new SendPushNotificationJob([
                    'reminderId' => (int) $reminder->id,
                ]));
            }
        }
    }

    private function resolveUsers(): array
    {
        if ($this->userId !== null) {
            return [$this->userId];
        }

        return (new Query())
            ->select('id')
            ->from('{{%user}}')
            ->column();
    }
}
