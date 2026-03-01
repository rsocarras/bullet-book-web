<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbTemplate extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_template}}';
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['owner_user_id'], 'integer', 'min' => 1],
            [['description'], 'string', 'max' => 500],
            [['name'], 'string', 'max' => 120],
            [['is_system', 'is_public'], 'boolean'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['owner_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['owner_user_id' => 'id']],
        ];
    }

    public function getOwnerUser()
    {
        return $this->hasOne(User::class, ['id' => 'owner_user_id']);
    }

    public function getTemplateBullets()
    {
        return $this->hasMany(BbTemplateBullet::class, ['template_id' => 'id'])
            ->andOnCondition(['deleted_at' => null])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getUserTemplates()
    {
        return $this->hasMany(BbUserTemplate::class, ['template_id' => 'id']);
    }
}
