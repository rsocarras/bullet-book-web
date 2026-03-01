<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbTemplateBullet extends BaseActiveRecord
{
    public static function tableName(): string
    {
        return '{{%bb_template_bullet}}';
    }

    public function rules(): array
    {
        return [
            [['template_id', 'bullet_id'], 'required'],
            [['template_id', 'bullet_id', 'sort_order'], 'integer'],
            [['is_default_active'], 'boolean'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['template_id', 'bullet_id'], 'unique', 'targetAttribute' => ['template_id', 'bullet_id']],
            [['template_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbTemplate::class, 'targetAttribute' => ['template_id' => 'id']],
            [['bullet_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbBullet::class, 'targetAttribute' => ['bullet_id' => 'id']],
        ];
    }

    public function getTemplate()
    {
        return $this->hasOne(BbTemplate::class, ['id' => 'template_id']);
    }

    public function getBullet()
    {
        return $this->hasOne(BbBullet::class, ['id' => 'bullet_id']);
    }
}
