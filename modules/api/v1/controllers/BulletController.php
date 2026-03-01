<?php

namespace app\modules\api\v1\controllers;

use app\models\BbBullet;
use yii\web\NotFoundHttpException;

class BulletController extends BaseApiController
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

    public function actionIndex($active = null): array
    {
        $query = BbBullet::find()
            ->where(['user_id' => $this->userId(), 'deleted_at' => null])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC]);

        if ($active !== null) {
            $query->andWhere(['is_active' => (int) filter_var($active, FILTER_VALIDATE_BOOLEAN)]);
        }

        $rows = array_map(static fn(BbBullet $model) => $model->toArray(), $query->all());

        return $this->success($rows);
    }

    public function actionView(int $id): array
    {
        return $this->success($this->findModel($id)->toArray());
    }

    public function actionCreate(): array
    {
        $model = new BbBullet();
        $model->setAttributes($this->bodyParams(), false);
        $model->user_id = $this->userId();

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

    private function findModel(int $id): BbBullet
    {
        $model = BbBullet::find()
            ->where(['id' => $id, 'user_id' => $this->userId(), 'deleted_at' => null])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Bullet not found.');
        }

        return $model;
    }
}
