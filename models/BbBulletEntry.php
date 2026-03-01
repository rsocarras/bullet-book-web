<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbBulletEntry extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_bullet_entry}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'bullet_id', 'entry_date'], 'required'],
            [['user_id', 'bullet_id', 'value_int'], 'integer'],
            [['value_decimal'], 'number'],
            [['entry_date'], 'date', 'format' => 'php:Y-m-d'],
            [['value_text', 'note'], 'string', 'max' => 1000],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['user_id', 'bullet_id', 'entry_date'], 'unique', 'targetAttribute' => ['user_id', 'bullet_id', 'entry_date']],
            [['value_int', 'value_decimal', 'value_text'], 'validateSingleValue'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['bullet_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbBullet::class, 'targetAttribute' => ['bullet_id' => 'id']],
        ];
    }

    public function validateSingleValue(string $attribute): void
    {
        $present = 0;
        foreach (['value_int', 'value_decimal', 'value_text'] as $field) {
            if ($this->{$field} !== null && $this->{$field} !== '') {
                $present++;
            }
        }

        if ($present > 1) {
            $this->addError($attribute, 'Only one of value_int, value_decimal or value_text can be set.');
        }
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getBullet()
    {
        return $this->hasOne(BbBullet::class, ['id' => 'bullet_id']);
    }

    public static function hasEntriesForDate(int $userId, string $date): bool
    {
        return static::find()
            ->where([
                'user_id' => $userId,
                'entry_date' => $date,
                'deleted_at' => null,
            ])
            ->exists();
    }

    /**
     * Returns entries keyed by bullet id for a single date.
     */
    public static function findEntriesByDate(int $userId, string $date): array
    {
        $models = static::find()
            ->where([
                'user_id' => $userId,
                'entry_date' => $date,
                'deleted_at' => null,
            ])
            ->all();

        $indexed = [];
        foreach ($models as $model) {
            $indexed[(int) $model->bullet_id] = $model;
        }

        return $indexed;
    }

    /**
     * Returns month entries for heatmap preview.
     */
    public static function findMonthEntries(int $userId, int $bulletId, string $startDate, string $endDate): array
    {
        return static::find()
            ->where([
                'user_id' => $userId,
                'bullet_id' => $bulletId,
                'deleted_at' => null,
            ])
            ->andWhere(['between', 'entry_date', $startDate, $endDate])
            ->orderBy(['entry_date' => SORT_ASC])
            ->all();
    }
}
