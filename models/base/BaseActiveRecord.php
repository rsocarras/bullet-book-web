<?php

namespace app\models\base;

use DateTimeImmutable;
use DateTimeZone;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

abstract class BaseActiveRecord extends ActiveRecord
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => $this->hasAttribute('created_at') ? 'created_at' : null,
                'updatedAtAttribute' => $this->hasAttribute('updated_at') ? 'updated_at' : null,
                'value' => new Expression('UTC_TIMESTAMP(6)'),
            ],
        ]);
    }

    public static function nowUtc(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    public function softDelete(?string $deletedAt = null): bool
    {
        if (!$this->hasAttribute('deleted_at')) {
            return false;
        }

        $this->setAttribute('deleted_at', $deletedAt ?? static::nowUtc());

        if ($this->hasAttribute('updated_at')) {
            $this->setAttribute('updated_at', static::nowUtc());
        }

        return $this->save(false, array_filter(['deleted_at', $this->hasAttribute('updated_at') ? 'updated_at' : null]));
    }

    public static function activeQueryAlias(string $alias = ''): string
    {
        return $alias !== '' ? $alias . '.' : '';
    }
}
