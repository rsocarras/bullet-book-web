<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbLabel extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_label}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'name'], 'required'],
            [['user_id'], 'integer'],
            [['name'], 'string', 'max' => 80],
            [['color'], 'string', 'max' => 16],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['user_id', 'name'], 'unique', 'targetAttribute' => ['user_id', 'name']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTaskLabels()
    {
        return $this->hasMany(BbTaskLabel::class, ['label_id' => 'id'])
            ->andOnCondition(['deleted_at' => null]);
    }

    public function getTasks()
    {
        return $this->hasMany(BbTask::class, ['id' => 'task_id'])
            ->via('taskLabels');
    }
}
