<?php

namespace app\jobs;

use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use app\models\BbReminder;
use app\services\PushNotificationService;

class SendPushNotificationJob extends BaseObject implements JobInterface
{
    public ?int $reminderId = null;

    public function execute($queue): void
    {
        $query = BbReminder::find()
            ->where(['deleted_at' => null])
            ->andWhere(['status' => BbReminder::STATUS_QUEUED]);

        if ($this->reminderId !== null) {
            $query->andWhere(['id' => $this->reminderId]);
        } else {
            $query->andWhere(['<=', 'fire_at', $this->nowUtc()]);
            $query->limit(100);
        }

        $service = new PushNotificationService();

        foreach ($query->all() as $reminder) {
            try {
                $sent = $service->sendReminder($reminder);
                $reminder->status = $sent ? BbReminder::STATUS_SENT : BbReminder::STATUS_FAILED;
                $reminder->save(false, ['status', 'updated_at']);
            } catch (Throwable $e) {
                $reminder->status = BbReminder::STATUS_FAILED;
                $reminder->save(false, ['status', 'updated_at']);
                Yii::error('SendPushNotificationJob failed: ' . $e->getMessage(), __METHOD__);
            }
        }
    }

    private function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s.u');
    }
}
