<?php

namespace app\controllers;

use Yii;
use app\models\BbBullet;
use app\models\BbBulletEntry;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class EntryController extends Controller
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
                    'quick-save' => ['POST'],
                ],
            ],
        ];
    }

    public function actionQuickSave(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $payload = Yii::$app->request->bodyParams;
        $userId = (int) Yii::$app->user->id;
        $bulletId = (int) ($payload['bullet_id'] ?? 0);
        $entryDate = (string) ($payload['entry_date'] ?? date('Y-m-d'));

        if ($bulletId <= 0) {
            return $this->jsonError('bullet_id is required.');
        }

        $bullet = BbBullet::find()
            ->where([
                'id' => $bulletId,
                'user_id' => $userId,
                'deleted_at' => null,
            ])
            ->one();

        if ($bullet === null) {
            throw new NotFoundHttpException('Bullet not found.');
        }

        $entry = BbBulletEntry::find()
            ->where([
                'user_id' => $userId,
                'bullet_id' => $bulletId,
                'entry_date' => $entryDate,
            ])
            ->one();

        $isNew = false;
        if ($entry === null) {
            $entry = new BbBulletEntry();
            $entry->user_id = $userId;
            $entry->bullet_id = $bulletId;
            $entry->entry_date = $entryDate;
            $isNew = true;
        }

        $entry->value_int = null;
        $entry->value_decimal = null;
        $entry->value_text = null;
        $entry->note = isset($payload['note']) ? trim((string) $payload['note']) : null;
        $entry->deleted_at = null;

        $validationError = $this->applyValueByInputType($entry, $bullet, $payload);
        if ($validationError !== null) {
            return $this->jsonError($validationError, [], 422);
        }

        if (!$entry->save()) {
            return $this->jsonError('Validation failed.', $entry->errors, 422);
        }

        return [
            'success' => true,
            'message' => $isNew ? 'Check-in guardado.' : 'Check-in actualizado.',
            'data' => $entry->toArray(),
        ];
    }

    /**
     * Validates and maps value_* fields depending on bullet.input_type.
     */
    private function applyValueByInputType(BbBulletEntry $entry, BbBullet $bullet, array $payload): ?string
    {
        switch ($bullet->input_type) {
            case BbBullet::INPUT_BINARY:
                $value = filter_var($payload['value_int'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                if ($value === null || !in_array((int) $value, [0, 1], true)) {
                    return 'Para bullet binary se requiere value_int = 0 o 1.';
                }
                $entry->value_int = (int) $value;
                return null;

            case BbBullet::INPUT_SCALE:
            case BbBullet::INPUT_STARS:
                $value = filter_var($payload['value_int'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    return 'Para bullet scale/stars se requiere value_int.';
                }

                $min = (int) ($bullet->scale_min ?? 1);
                $max = (int) ($bullet->scale_max ?? $min);
                if ($value < $min || $value > $max) {
                    return "El valor debe estar entre {$min} y {$max}.";
                }

                $entry->value_int = (int) $value;
                return null;

            case BbBullet::INPUT_NUMERIC:
                if (isset($payload['value_decimal']) && $payload['value_decimal'] !== '') {
                    if (!is_numeric($payload['value_decimal'])) {
                        return 'value_decimal debe ser numérico.';
                    }
                    $entry->value_decimal = (float) $payload['value_decimal'];
                    return null;
                }

                if (isset($payload['value_int']) && $payload['value_int'] !== '') {
                    $value = filter_var($payload['value_int'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        return 'value_int debe ser entero.';
                    }
                    $entry->value_int = (int) $value;
                    return null;
                }

                return 'Para bullet numeric se requiere value_decimal o value_int.';

            case BbBullet::INPUT_TEXT:
                $value = trim((string) ($payload['value_text'] ?? ''));
                if ($value === '') {
                    return 'Para bullet text se requiere value_text.';
                }
                $entry->value_text = $value;
                return null;

            default:
                return 'input_type no soportado.';
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
