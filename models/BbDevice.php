<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbDevice extends BaseActiveRecord
{
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_WEB = 'web';

    public static function tableName(): string
    {
        return '{{%bb_device}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'platform', 'device_uid'], 'required'],
            [['user_id'], 'integer', 'min' => 1],
            [['platform'], 'in', 'range' => [self::PLATFORM_IOS, self::PLATFORM_ANDROID, self::PLATFORM_WEB]],
            [['device_uid'], 'string', 'max' => 128],
            [['push_token'], 'string', 'max' => 512],
            [['last_sync_at', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['user_id', 'device_uid'], 'unique', 'targetAttribute' => ['user_id', 'device_uid']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getAccessTokens()
    {
        return $this->hasMany(UserAccessToken::class, ['device_id' => 'id']);
    }

    public function getReminders()
    {
        return $this->hasMany(BbReminder::class, ['device_id' => 'id']);
    }
}
