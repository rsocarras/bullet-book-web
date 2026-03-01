<?php

namespace app\modules\api\v1\controllers;

use Yii;
use yii\db\Query;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use app\models\BbBullet;
use app\models\BbTemplate;
use app\models\BbTemplateBullet;

class TemplateController extends BaseApiController
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

    /**
     * GET /api/v1/templates?system=1
     * Returns templates summary with bullets_count.
     */
    public function actionIndex($system = null): array
    {
        $query = BbTemplate::find()->alias('t')->where(['t.deleted_at' => null]);

        $systemFilter = filter_var($system, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($systemFilter === true) {
            $query->andWhere(['t.is_system' => 1]);
        } elseif ($systemFilter === false) {
            $query->andWhere(['t.is_system' => 0, 't.owner_user_id' => $this->userId()]);
        } else {
            $query->andWhere(['or', ['t.is_system' => 1], ['t.owner_user_id' => $this->userId()]]);
        }

        $templates = $query
            ->orderBy(['t.is_system' => SORT_DESC, 't.name' => SORT_ASC, 't.id' => SORT_ASC])
            ->all();

        $templateIds = array_map(static fn(BbTemplate $t) => (int) $t->id, $templates);
        $counts = [];
        if (!empty($templateIds)) {
            $counts = (new Query())
                ->select(['template_id', 'cnt' => 'COUNT(*)'])
                ->from(BbTemplateBullet::tableName())
                ->where(['template_id' => $templateIds, 'deleted_at' => null])
                ->groupBy(['template_id'])
                ->indexBy('template_id')
                ->all();
        }

        $data = [];
        foreach ($templates as $template) {
            $templateId = (int) $template->id;
            $data[] = [
                'id' => $templateId,
                'name' => $template->name,
                'description' => $template->description,
                'bullets_count' => isset($counts[$templateId]) ? (int) $counts[$templateId]['cnt'] : 0,
            ];
        }

        return $this->success($data);
    }

    /**
     * GET /api/v1/templates/{id}
     * Returns template details and bullets preview ordered by template sort_order.
     */
    public function actionView(int $id): array
    {
        $template = $this->findReadableModel($id);

        $tbRows = BbTemplateBullet::find()
            ->alias('tb')
            ->innerJoin(['b' => BbBullet::tableName()], 'b.id = tb.bullet_id')
            ->where(['tb.template_id' => $template->id, 'tb.deleted_at' => null, 'b.deleted_at' => null])
            ->orderBy(['tb.sort_order' => SORT_ASC, 'tb.id' => SORT_ASC])
            ->select([
                'tb.sort_order',
                'b.id',
                'b.name',
                'b.bullet_type',
                'b.input_type',
                'b.scale_min',
                'b.scale_max',
                'b.scale_labels',
                'b.icon',
                'b.color',
                'b.weight',
            ]);

        if ($this->hasBulletColumn('is_system')) {
            // Only system bullets in template preview when column is available.
            $tbRows->andWhere(['b.is_system' => 1]);
        } else {
            // TODO: bb_bullet.is_system is missing in this environment.
            // Using template linkage as the source-of-truth for onboarding preview.
        }

        $bullets = [];
        foreach ($tbRows->asArray()->all() as $row) {
            $scaleLabels = $row['scale_labels'];
            if (is_string($scaleLabels) && $scaleLabels !== '') {
                $decoded = json_decode($scaleLabels, true);
                $scaleLabels = json_last_error() === JSON_ERROR_NONE ? $decoded : $scaleLabels;
            }

            $bullets[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'bullet_type' => $row['bullet_type'],
                'input_type' => $row['input_type'],
                'scale_min' => $row['scale_min'] !== null ? (int) $row['scale_min'] : null,
                'scale_max' => $row['scale_max'] !== null ? (int) $row['scale_max'] : null,
                'scale_labels' => $scaleLabels,
                'icon' => $row['icon'],
                'color' => $row['color'],
                'weight' => $row['weight'] !== null ? (float) $row['weight'] : null,
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $this->success([
            'id' => (int) $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'bullets' => $bullets,
        ]);
    }

    public function actionCreate(): array
    {
        $model = new BbTemplate();
        $model->setAttributes($this->bodyParams(), false);
        $model->owner_user_id = $this->userId();
        $model->is_system = 0;

        if (!$model->save()) {
            return $this->failValidation($model->errors, 422);
        }

        return $this->success($model->toArray(), 201);
    }

    public function actionUpdate(int $id): array
    {
        $model = $this->findWritableModel($id);
        $model->setAttributes($this->bodyParams(), false);
        $model->owner_user_id = $this->userId();
        $model->is_system = 0;

        if (!$model->save()) {
            return $this->failValidation($model->errors, 422);
        }

        return $this->success($model->toArray());
    }

    public function actionDelete(int $id): array
    {
        $model = $this->findWritableModel($id);
        $model->softDelete();

        return $this->success(['id' => $id, 'deleted' => true]);
    }

    private function findReadableModel(int $id): BbTemplate
    {
        $model = BbTemplate::find()
            ->where(['id' => $id, 'deleted_at' => null])
            ->andWhere(['or', ['owner_user_id' => $this->userId()], ['is_system' => 1]])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Template not found.');
        }

        return $model;
    }

    private function findWritableModel(int $id): BbTemplate
    {
        $model = BbTemplate::find()
            ->where(['id' => $id, 'deleted_at' => null, 'owner_user_id' => $this->userId()])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Template not found.');
        }

        if ((int) $model->is_system === 1) {
            throw new ForbiddenHttpException('System templates cannot be modified.');
        }

        return $model;
    }

    private function hasBulletColumn(string $column): bool
    {
        $schema = Yii::$app->db->schema->getTableSchema(BbBullet::tableName(), true);
        return $schema !== null && isset($schema->columns[$column]);
    }
}
