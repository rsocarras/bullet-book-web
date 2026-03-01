<?php

namespace app\modules\api\v1\controllers;

use Yii;
use app\services\AuthService;

class AuthController extends BaseApiController
{
    public function verbs(): array
    {
        return [
            'login' => ['POST'],
            'logout' => ['POST'],
        ];
    }

    protected function authExceptActions(): array
    {
        return ['login'];
    }

    public function actionLogin(): array
    {
        $payload = $this->bodyParams();
        $identifier = trim((string) ($payload['identifier'] ?? $payload['email'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return $this->fail('identifier and password are required.', [], 422);
        }

        $service = new AuthService();
        $result = $service->login($identifier, $password, (array) ($payload['device'] ?? []));

        return $this->success($result, 200);
    }

    public function actionLogout(): array
    {
        $header = (string) Yii::$app->request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
            return $this->fail('Bearer token is required.', [], 401);
        }

        $service = new AuthService();
        $revoked = $service->revokeToken($matches[1], $this->userId());

        return $this->success([
            'revoked' => $revoked,
        ]);
    }
}
