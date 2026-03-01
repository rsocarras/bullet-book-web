<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbUserSetup extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_user_setup}}';
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['onboarded_at', 'created_at', 'updated_at'], 'safe'],
            [['timezone'], 'string', 'max' => 64],
            [['user_id'], 'unique'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
