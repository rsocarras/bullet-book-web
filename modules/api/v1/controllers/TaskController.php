<?php

namespace app\modules\api\v1\controllers;

use app\models\BbTask;
use yii\web\NotFoundHttpException;

class TaskController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    public function actionIndex($status = null, $project_id = null, $q = null): array
    {
        $query = BbTask::find()
            ->where(['user_id' => $this->userId(), 'deleted_at' => null])
            ->orderBy(['due_at' => SORT_ASC, 'updated_at' => SORT_DESC, 'id' => SORT_DESC]);

        if ($status !== null) {
            $query->andWhere(['status' => (string) $status]);
        }

        if ($project_id !== null && $project_id !== '') {
            $query->andWhere(['project_id' => (int) $project_id]);
        }

        if ($q !== null && trim((string) $q) !== '') {
            $term = trim((string) $q);
            $query->andWhere(['or', ['like', 'title', $term], ['like', 'description', $term]]);
        }

        $rows = array_map(static fn(BbTask $model) => $model->toArray(), $query->all());
        return $this->success($rows);
    }

    public function actionView(int $id): array
    {
        return $this->success($this->findModel($id)->toArray());
    }

    public function actionCreate(): array
    {
        $model = new BbTask();
        $model->setAttributes($this->bodyParams(), false);
        $model->user_id = $this->userId();

        if ($model->status === null || $model->status === '') {
            $model->status = BbTask::STATUS_INBOX;
        }

        if ($model->priority === null || $model->priority === '') {
            $model->priority = BbTask::PRIORITY_MEDIUM;
        }

        if (!$model->save()) {
            return $this->fail('Validation failed.', $model->errors, 422);
        }

        return $this->success($model->toArray(), 201);
    }

    public function actionUpdate(int $id): array
    {
        $model = $this->findModel($id);
        $model->setAttributes($this->bodyParams(), false);
        $model->user_id = $this->userId();

        if (!$model->save()) {
            return $this->fail('Validation failed.', $model->errors, 422);
        }

        return $this->success($model->toArray());
    }

    public function actionDelete(int $id): array
    {
        $model = $this->findModel($id);
        $model->softDelete();

        return $this->success(['id' => $id, 'deleted' => true]);
    }

    private function findModel(int $id): BbTask
    {
        $model = BbTask::find()
            ->where(['id' => $id, 'user_id' => $this->userId(), 'deleted_at' => null])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $model;
    }
}
