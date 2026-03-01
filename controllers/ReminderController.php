<?php

namespace app\controllers;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use app\models\BbReminder;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class ReminderController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'daily-checkin' => ['POST'],
                ],
            ],
        ];
    }

    public function actionDailyCheckin(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $payload = Yii::$app->request->bodyParams;
        $time = trim((string) ($payload['time'] ?? ''));
        $timezone = trim((string) ($payload['timezone'] ?? 'UTC'));
        $userId = (int) Yii::$app->user->id;

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            return $this->jsonError('Formato de hora inválido. Usa HH:MM.', [], 422);
        }

        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Throwable $e) {
            return $this->jsonError('Timezone inválido.', [], 422);
        }

        $localNow = new DateTimeImmutable('now', $tz);
        $localFire = $localNow->setTime((int) $matches[1], (int) $matches[2], 0, 0);
        if ($localFire <= $localNow) {
            $localFire = $localFire->modify('+1 day');
        }

        $fireAtUtc = $localFire->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        // Keep one active daily check-in reminder per user for MVP.
        $reminder = BbReminder::find()
            ->where([
                'user_id' => $userId,
                'kind' => BbReminder::KIND_DAILY_CHECKIN,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($reminder === null) {
            $reminder = new BbReminder();
            $reminder->user_id = $userId;
            $reminder->kind = BbReminder::KIND_DAILY_CHECKIN;
        }

        $reminder->channel = BbReminder::CHANNEL_PUSH;
        $reminder->status = BbReminder::STATUS_SCHEDULED;
        $reminder->timezone = $timezone;
        $reminder->fire_at = $fireAtUtc;
        $reminder->cron_expr = 'DAILY ' . $time;
        $reminder->payload = [
            'title' => 'Check-in diario',
            'body' => 'Es momento de registrar tus bullets de hoy.',
        ];

        if (!$reminder->save()) {
            return $this->jsonError('No se pudo guardar el recordatorio.', $reminder->errors, 422);
        }

        return [
            'success' => true,
            'message' => 'Recordatorio diario configurado.',
            'data' => $reminder->toArray(),
        ];
    }

    private function jsonError(string $message, array $errors = [], int $statusCode = 400): array
    {
        Yii::$app->response->statusCode = $statusCode;
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];
    }
}
