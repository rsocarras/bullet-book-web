<?php

namespace app\services;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use app\models\BbDevice;
use app\models\User;
use app\models\UserAccessToken;
use yii\web\UnauthorizedHttpException;
use yii\web\UnprocessableEntityHttpException;

class AuthService
{
    private const DEFAULT_TOKEN_TTL_DAYS = 30;

    public function login(string $identifier, string $password, array $devicePayload = []): array
    {
        $user = User::findByIdentifier(trim($identifier));

        if ($user === null || !$user->validatePassword($password)) {
            throw new UnauthorizedHttpException('Invalid credentials.');
        }

        $device = $this->upsertDevice($user->id, $devicePayload);
        return $this->issueAccessToken($user, $device?->id);
    }

    public function issueAccessToken(User $user, ?int $deviceId = null): array
    {
        $plainToken = Yii::$app->security->generateRandomString(64);
        $now = $this->nowUtc();
        $expiresAt = $this->nowUtc()->modify('+' . self::DEFAULT_TOKEN_TTL_DAYS . ' days');

        $token = new UserAccessToken();
        $token->user_id = (int) $user->id;
        $token->device_id = $deviceId;
        $token->token_hash = hash('sha256', $plainToken);
        $token->expires_at = $expiresAt->format('Y-m-d H:i:s.u');
        $token->last_used_at = $now->format('Y-m-d H:i:s.u');
        $token->revoked_at = null;

        if (!$token->save()) {
            throw new UnprocessableEntityHttpException('Unable to create access token.');
        }

        return [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
            'user' => [
                'id' => (int) $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ];
    }

    public function revokeToken(string $plainToken, int $userId): bool
    {
        $token = UserAccessToken::find()
            ->where(['token_hash' => hash('sha256', $plainToken), 'user_id' => $userId])
            ->andWhere(['revoked_at' => null])
            ->one();

        if ($token === null) {
            return false;
        }

        $token->revoked_at = $this->nowUtc()->format('Y-m-d H:i:s.u');
        return (bool) $token->save(false, ['revoked_at', 'updated_at']);
    }

    private function upsertDevice(int $userId, array $payload): ?BbDevice
    {
        if (!isset($payload['platform'], $payload['device_uid'])) {
            return null;
        }

        $device = BbDevice::find()
            ->where(['user_id' => $userId, 'device_uid' => $payload['device_uid']])
            ->one();

        if ($device === null) {
            $device = new BbDevice();
            $device->user_id = $userId;
            $device->device_uid = (string) $payload['device_uid'];
        }

        $device->platform = (string) $payload['platform'];
        $device->push_token = isset($payload['push_token']) ? (string) $payload['push_token'] : $device->push_token;
        $device->last_sync_at = $this->nowUtc()->format('Y-m-d H:i:s.u');
        $device->deleted_at = null;

        if (!$device->save()) {
            throw new UnprocessableEntityHttpException('Unable to register device.');
        }

        return $device;
    }

    private function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
