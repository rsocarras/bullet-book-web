<?php

namespace app\modules\api\v1\controllers;

use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\Controller;
use yii\web\Response;

abstract class BaseApiController extends Controller
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
                'text/json' => Response::FORMAT_JSON,
            ],
        ];

        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'except' => $this->authExceptActions(),
            'authMethods' => [
                HttpBearerAuth::class,
            ],
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => $this->verbs(),
        ];

        return $behaviors;
    }

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->user->enableSession = false;
        Yii::$app->user->loginUrl = null;

        return parent::beforeAction($action);
    }

    protected function authExceptActions(): array
    {
        return [];
    }

    protected function bodyParams(): array
    {
        return Yii::$app->request->getBodyParams();
    }

    protected function userId(): int
    {
        return (int) Yii::$app->user->id;
    }

    protected function success($data = null, int $statusCode = 200): array
    {
        Yii::$app->response->statusCode = $statusCode;
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    protected function fail(string $message, array $errors = [], int $statusCode = 422): array
    {
        Yii::$app->response->statusCode = $statusCode;

        return [
            'success' => false,
            'error' => [
                'message' => $message,
                'details' => $errors,
            ],
        ];
    }

    protected function failValidation(array $errors, int $statusCode = 422): array
    {
        Yii::$app->response->statusCode = $statusCode;

        return [
            'success' => false,
            'errors' => $errors,
        ];
    }
}
