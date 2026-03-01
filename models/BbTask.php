<?php

namespace app\models;

use app\models\base\BaseActiveRecord;

class BbTask extends BaseActiveRecord
{
    public const STATUS_INBOX = 'inbox';
    public const STATUS_TODO = 'todo';
    public const STATUS_DOING = 'doing';
    public const STATUS_DONE = 'done';
    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public static function tableName(): string
    {
        return '{{%bb_task}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'title'], 'required'],
            [['user_id', 'project_id'], 'integer'],
            [['description'], 'string'],
            [['title'], 'string', 'max' => 200],
            [['status'], 'in', 'range' => [self::STATUS_INBOX, self::STATUS_TODO, self::STATUS_DOING, self::STATUS_DONE, self::STATUS_ARCHIVED]],
            [['priority'], 'in', 'range' => [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT]],
            [['due_at', 'completed_at', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['project_id'], 'exist', 'skipOnError' => true, 'targetClass' => BbProject::class, 'targetAttribute' => ['project_id' => 'id']],
        ];
    }

    public function beforeSave($insert): bool
    {
        if ($this->status === self::STATUS_DONE && $this->completed_at === null) {
            $this->completed_at = static::nowUtc();
        }

        if ($this->status !== self::STATUS_DONE) {
            $this->completed_at = null;
        }

        return parent::beforeSave($insert);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getProject()
    {
        return $this->hasOne(BbProject::class, ['id' => 'project_id']);
    }

    public function getTaskLabels()
    {
        return $this->hasMany(BbTaskLabel::class, ['task_id' => 'id'])
            ->andOnCondition(['deleted_at' => null]);
    }

    public function getLabels()
    {
        return $this->hasMany(BbLabel::class, ['id' => 'label_id'])
            ->via('taskLabels');
    }

    public static function findUpcoming(int $userId, int $days = 7): array
    {
        $from = static::nowUtc();
        $to = gmdate('Y-m-d H:i:s.u', strtotime('+' . $days . ' days'));

        return static::find()
            ->where([
                'user_id' => $userId,
                'deleted_at' => null,
            ])
            ->andWhere(['!=', 'status', self::STATUS_ARCHIVED])
            ->andWhere(['IS NOT', 'due_at', null])
            ->andWhere(['between', 'due_at', $from, $to])
            ->orderBy(['due_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(7)
            ->all();
    }

    public static function countByStatus(int $userId): array
    {
        $rows = static::find()
            ->select(['status', 'total' => 'COUNT(*)'])
            ->where([
                'user_id' => $userId,
                'deleted_at' => null,
            ])
            ->groupBy(['status'])
            ->asArray()
            ->all();

        $defaults = [
            self::STATUS_INBOX => 0,
            self::STATUS_TODO => 0,
            self::STATUS_DOING => 0,
            self::STATUS_DONE => 0,
            self::STATUS_ARCHIVED => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $defaults[$status] = (int) $row['total'];
        }

        return $defaults;
    }
}
