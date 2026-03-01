<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbBullet extends BaseActiveRecord
{
    public const TYPE_HABIT = 'habit';
    public const TYPE_FEELING = 'feeling';
    public const TYPE_FINANCE = 'finance';
    public const TYPE_GOAL = 'goal';

    public const INPUT_BINARY = 'binary';
    public const INPUT_SCALE = 'scale';
    public const INPUT_STARS = 'stars';
    public const INPUT_NUMERIC = 'numeric';
    public const INPUT_TEXT = 'text';

    public static function tableName(): string
    {
        return '{{%bb_bullet}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'name', 'bullet_type', 'input_type'], 'required'],
            [['user_id', 'scale_min', 'scale_max'], 'integer'],
            [['weight'], 'number'],
            [['is_active'], 'boolean'],
            [['name'], 'string', 'max' => 120],
            [['icon'], 'string', 'max' => 64],
            [['color'], 'string', 'max' => 16],
            [['scale_labels', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['bullet_type'], 'in', 'range' => [self::TYPE_HABIT, self::TYPE_FEELING, self::TYPE_FINANCE, self::TYPE_GOAL]],
            [['input_type'], 'in', 'range' => [self::INPUT_BINARY, self::INPUT_SCALE, self::INPUT_STARS, self::INPUT_NUMERIC, self::INPUT_TEXT]],
            [['scale_labels'], 'validateJsonValue'],
            [['input_type'], 'validateScaleSettings'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
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

    public function validateScaleSettings(string $attribute): void
    {
        $needsScale = in_array($this->input_type, [self::INPUT_SCALE, self::INPUT_STARS], true);

        if ($needsScale) {
            if ($this->scale_min === null || $this->scale_max === null) {
                $this->addError('scale_min', 'scale_min and scale_max are required for scale/stars.');
                return;
            }

            if ((int) $this->scale_min >= (int) $this->scale_max) {
                $this->addError('scale_min', 'scale_min must be lower than scale_max.');
            }

            return;
        }

        if ($this->scale_min !== null || $this->scale_max !== null || $this->scale_labels !== null) {
            $this->addError($attribute, 'Scale fields are only allowed for scale/stars input types.');
        }
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTemplateBullets()
    {
        return $this->hasMany(BbTemplateBullet::class, ['bullet_id' => 'id'])
            ->andOnCondition(['deleted_at' => null]);
    }

    public function getEntries()
    {
        return $this->hasMany(BbBulletEntry::class, ['bullet_id' => 'id'])
            ->andOnCondition(['deleted_at' => null]);
    }

    /**
     * Returns active bullets for a user ordered for fast dashboard rendering.
     */
    public static function findActiveByUser(int $userId): array
    {
        return static::find()
            ->where([
                'user_id' => $userId,
                'is_active' => 1,
                'deleted_at' => null,
            ])
            ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_ASC])
            ->all();
    }
}
