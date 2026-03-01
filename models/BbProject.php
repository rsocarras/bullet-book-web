<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbProject extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_project}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'name'], 'required'],
            [['user_id'], 'integer'],
            [['name'], 'string', 'max' => 160],
            [['description'], 'string', 'max' => 1000],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTasks()
    {
        return $this->hasMany(BbTask::class, ['project_id' => 'id'])
            ->andOnCondition(['deleted_at' => null]);
    }
}
