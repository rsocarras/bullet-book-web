<?php

namespace app\services;

use Yii;
use app\models\BbDevice;
use app\models\BbReminder;

class PushNotificationService
{
    public function sendReminder(BbReminder $reminder): bool
    {
        $targets = [];

        if ($reminder->device_id !== null) {
            $device = BbDevice::findOne($reminder->device_id);
            if ($device !== null && $device->push_token !== null && $device->deleted_at === null) {
                $targets[] = $device->push_token;
            }
        } else {
            $targets = BbDevice::find()
                ->select('push_token')
                ->where(['user_id' => $reminder->user_id, 'deleted_at' => null])
                ->andWhere(['IS NOT', 'push_token', null])
                ->column();
        }

        if ($reminder->channel === BbReminder::CHANNEL_EMAIL) {
            Yii::info('Email summary/reminder queued for user ' . $reminder->user_id, __METHOD__);
            return true;
        }

        if (empty($targets)) {
            Yii::warning('No push targets for reminder #' . $reminder->id, __METHOD__);
            return false;
        }

        $payload = is_array($reminder->payload) ? $reminder->payload : json_decode((string) $reminder->payload, true);
        $title = $payload['title'] ?? 'Bullet Book';
        $body = $payload['body'] ?? 'You have a reminder.';

        // Placeholder transport: replace with FCM/APNs provider adapter.
        Yii::info('Push sent to ' . count($targets) . ' devices. title=' . $title . ' body=' . $body, __METHOD__);

        return true;
    }
}
