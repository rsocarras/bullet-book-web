<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbUserTemplate extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_user_template}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'template_id'], 'required'],
            [['user_id', 'template_id'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_id', 'template_id'], 'unique', 'targetAttribute' => ['user_id', 'template_id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbTemplate::class, 'targetAttribute' => ['template_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTemplate()
    {
        return $this->hasOne(BbTemplate::class, ['id' => 'template_id']);
    }
}
