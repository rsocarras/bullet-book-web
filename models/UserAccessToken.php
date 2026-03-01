<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class UserAccessToken extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_access_token}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'token_hash', 'expires_at'], 'required'],
            [['user_id', 'device_id'], 'integer', 'min' => 1],
            [['expires_at', 'last_used_at', 'revoked_at', 'created_at', 'updated_at'], 'safe'],
            [['token_hash'], 'string', 'length' => 64],
            [['token_hash'], 'unique'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['device_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbDevice::class, 'targetAttribute' => ['device_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getDevice()
    {
        return $this->hasOne(BbDevice::class, ['id' => 'device_id']);
    }

    public static function findActiveByToken(string $plainToken): ?self
    {
        return static::find()
            ->where(['token_hash' => hash('sha256', $plainToken)])
            ->andWhere(['revoked_at' => null])
            ->andWhere(['>', 'expires_at', static::nowUtc()])
            ->one();
    }
}
