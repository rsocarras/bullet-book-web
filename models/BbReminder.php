<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbReminder extends BaseActiveRecord
{
    public const KIND_TASK = 'TASK';
    public const KIND_DAILY_CHECKIN = 'DAILY_CHECKIN';
    public const KIND_WEEKLY_SUMMARY = 'WEEKLY_SUMMARY';
    public const KIND_BULLET = 'BULLET';

    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_EMAIL = 'email';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%bb_reminder}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'kind'], 'required'],
            [['user_id', 'device_id', 'entity_id'], 'integer'],
            [['kind'], 'in', 'range' => [self::KIND_TASK, self::KIND_DAILY_CHECKIN, self::KIND_WEEKLY_SUMMARY, self::KIND_BULLET]],
            [['channel'], 'in', 'range' => [self::CHANNEL_PUSH, self::CHANNEL_EMAIL]],
            [['status'], 'in', 'range' => [self::STATUS_SCHEDULED, self::STATUS_QUEUED, self::STATUS_SENT, self::STATUS_CANCELED, self::STATUS_FAILED]],
            [['fire_at', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['cron_expr', 'timezone'], 'string', 'max' => 64],
            [['payload'], 'safe'],
            [['payload'], 'validateJsonValue'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['device_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbDevice::class, 'targetAttribute' => ['device_id' => 'id']],
        ];
    }

    public function validateJsonValue(string $attribute): void
    {
        if ($this->{$attribute} === null || $this->{$attribute} === '') {
            return;
        }

        if (is_array($this->{$attribute})) {
            return;
        }

        json_decode((string) $this->{$attribute}, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($attribute, 'Invalid JSON value.');
        }
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getDevice()
    {
        return $this->hasOne(BbDevice::class, ['id' => 'device_id']);
    }

    public static function findUpcoming(int $userId, int $limit = 5): array
    {
        return static::find()
            ->where([
                'user_id' => $userId,
                'status' => self::STATUS_SCHEDULED,
                'deleted_at' => null,
            ])
            ->andWhere(['>=', 'fire_at', static::nowUtc()])
            ->orderBy(['fire_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->all();
    }
}
