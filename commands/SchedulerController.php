<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\jobs\SendPushNotificationJob;
use app\jobs\WeeklySummaryJob;
use app\models\BbReminder;

class SchedulerController extends Controller
{
    public function actionEnqueueDueReminders(int $limit = 200): int
    {
        if (!Yii::$app->has('queue')) {
            $this->stderr("Queue component is not configured.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $due = BbReminder::find()
            ->where([
                'status' => BbReminder::STATUS_SCHEDULED,
                'deleted_at' => null,
            ])
            ->andWhere(['<=', 'fire_at', gmdate('Y-m-d H:i:s.u')])
            ->orderBy(['fire_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        $count = 0;
        foreach ($due as $reminder) {
            $reminder->status = BbReminder::STATUS_QUEUED;
            $reminder->save(false, ['status', 'updated_at']);

            Yii::$app->queue->push(new SendPushNotificationJob([
                'reminderId' => (int) $reminder->id,
            ]));
            $count++;
        }

        $this->stdout("Queued reminders: {$count}\n");
        return ExitCode::OK;
    }

    public function actionEnqueueWeeklySummary(?int $userId = null): int
    {
        if (!Yii::$app->has('queue')) {
            $this->stderr("Queue component is not configured.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        Yii::$app->queue->push(new WeeklySummaryJob([
            'userId' => $userId,
        ]));

        $this->stdout("Weekly summary job enqueued.\n");
        return ExitCode::OK;
    }
}
