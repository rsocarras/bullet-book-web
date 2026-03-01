<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbTaskLabel extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_task_label}}';
    }

    public function rules(): array
    {
        return [
            [['task_id', 'label_id'], 'required'],
            [['task_id', 'label_id'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['task_id', 'label_id'], 'unique', 'targetAttribute' => ['task_id', 'label_id']],
            [['task_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbTask::class, 'targetAttribute' => ['task_id' => 'id']],
            [['label_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbLabel::class, 'targetAttribute' => ['label_id' => 'id']],
        ];
    }

    public function getTask()
    {
        return $this->hasOne(BbTask::class, ['id' => 'task_id']);
    }

    public function getLabel()
    {
        return $this->hasOne(BbLabel::class, ['id' => 'label_id']);
    }
}
