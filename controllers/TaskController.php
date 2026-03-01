<?php

namespace app\controllers;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use app\models\BbProject;
use app\models\BbTask;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class TaskController extends Controller
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
                    'quick-create' => ['POST'],
                    'quick-update' => ['PATCH', 'POST'],
                ],
            ],
        ];
    }

    public function actionQuickCreate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $payload = Yii::$app->request->bodyParams;
        $title = trim((string) ($payload['title'] ?? ''));
        $userId = (int) Yii::$app->user->id;

        if ($title === '') {
            return $this->jsonError('El título es obligatorio.', [], 422);
        }

        $task = new BbTask();
        $task->user_id = $userId;
        $task->title = $title;
        $task->description = isset($payload['description']) ? trim((string) $payload['description']) : null;
        $task->status = BbTask::STATUS_INBOX;
        $task->priority = trim((string) ($payload['priority'] ?? BbTask::PRIORITY_MEDIUM));
        $task->due_at = $this->normalizeDateTime($payload['due_at'] ?? null);

        if (!empty($payload['project_id'])) {
            $projectId = (int) $payload['project_id'];
            $project = BbProject::find()
                ->where(['id' => $projectId, 'user_id' => $userId, 'deleted_at' => null])
                ->one();
            if ($project === null) {
                throw new NotFoundHttpException('Project not found.');
            }
            $task->project_id = $projectId;
        }

        if (!$task->save()) {
            return $this->jsonError('No se pudo crear la tarea.', $task->errors, 422);
        }

        return [
            'success' => true,
            'message' => 'Tarea creada.',
            'data' => $task->toArray(),
        ];
    }

    public function actionQuickUpdate(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $task = BbTask::find()
            ->where([
                'id' => $id,
                'user_id' => (int) Yii::$app->user->id,
                'deleted_at' => null,
            ])
            ->one();

        if ($task === null) {
            throw new NotFoundHttpException('Task not found.');
        }

        $payload = Yii::$app->request->bodyParams;

        if (array_key_exists('title', $payload)) {
            $task->title = trim((string) $payload['title']);
        }

        if (array_key_exists('description', $payload)) {
            $task->description = trim((string) $payload['description']);
        }

        if (array_key_exists('status', $payload)) {
            $task->status = trim((string) $payload['status']);
        }

        if (array_key_exists('priority', $payload)) {
            $task->priority = trim((string) $payload['priority']);
        }

        if (array_key_exists('due_at', $payload)) {
            $task->due_at = $this->normalizeDateTime($payload['due_at']);
        }

        if (!$task->save()) {
            return $this->jsonError('No se pudo actualizar la tarea.', $task->errors, 422);
        }

        return [
            'success' => true,
            'message' => 'Tarea actualizada.',
            'data' => $task->toArray(),
        ];
    }

    private function normalizeDateTime($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable((string) $value, new DateTimeZone(Yii::$app->timeZone ?: 'UTC'));
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (\Throwable $e) {
            return null;
        }
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
