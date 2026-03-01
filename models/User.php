<?php

namespace app\models;

use DateTimeImmutable;
use DateTimeZone;
use Yii;
use Da\User\Model\User as BaseUser;

class User extends BaseUser
{
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $accessToken = UserAccessToken::find()
            ->where(['token_hash' => hash('sha256', $token), 'revoked_at' => null])
            ->andWhere(['>', 'expires_at', static::nowUtc()])
            ->one();

        if ($accessToken === null) {
            return null;
        }

        $accessToken->last_used_at = static::nowUtc();
        $accessToken->save(false, ['last_used_at', 'updated_at']);

        Yii::$app->params['currentAccessTokenId'] = (int) $accessToken->id;

        return static::findIdentity((int) $accessToken->user_id);
    }

    public static function findByUsername(string $username): ?self
    {
        return static::find()->where(['username' => $username])->one();
    }

    public static function findByIdentifier(string $identifier): ?self
    {
        return static::find()
            ->where(['username' => $identifier])
            ->orWhere(['email' => $identifier])
            ->one();
    }

    public function validatePassword(string $password): bool
    {
        if (empty($this->password_hash)) {
            return false;
        }

        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function getAccessTokens()
    {
        return $this->hasMany(UserAccessToken::class, ['user_id' => 'id']);
    }

    public function getDevices()
    {
        return $this->hasMany(BbDevice::class, ['user_id' => 'id']);
    }

    private static function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
