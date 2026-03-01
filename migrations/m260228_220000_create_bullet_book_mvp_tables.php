<?php

use yii\db\Migration;

class m260228_220000_create_bullet_book_mvp_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        $this->createTable('{{%bb_device}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'platform' => "ENUM('ios','android','web') NOT NULL",
            'device_uid' => $this->string(128)->notNull(),
            'push_token' => $this->string(512)->null(),
            'last_sync_at' => $this->dateTime(6)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('uq_device_user_uid', '{{%bb_device}}', ['user_id', 'device_uid'], true);
        $this->createIndex('idx_device_user', '{{%bb_device}}', ['user_id']);
        $this->createIndex('idx_device_user_updated', '{{%bb_device}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_device_user', '{{%bb_device}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%user_access_token}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'device_id' => $this->bigInteger()->unsigned()->null(),
            'token_hash' => $this->char(64)->notNull(),
            'expires_at' => $this->dateTime(6)->notNull(),
            'last_used_at' => $this->dateTime(6)->null(),
            'revoked_at' => $this->dateTime(6)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
        ], $tableOptions);
        $this->createIndex('uq_token_hash', '{{%user_access_token}}', ['token_hash'], true);
        $this->createIndex('idx_uat_user', '{{%user_access_token}}', ['user_id']);
        $this->createIndex('idx_uat_expires', '{{%user_access_token}}', ['expires_at']);
        $this->addForeignKey('fk_uat_user', '{{%user_access_token}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_uat_device', '{{%user_access_token}}', 'device_id', '{{%bb_device}}', 'id', 'SET NULL', 'RESTRICT');

        $this->createTable('{{%bb_template}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'owner_user_id' => $this->integer()->null(),
            'name' => $this->string(120)->notNull(),
            'description' => $this->string(500)->null(),
            'is_system' => $this->boolean()->notNull()->defaultValue(false),
            'is_public' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('idx_template_owner', '{{%bb_template}}', ['owner_user_id']);
        $this->createIndex('idx_template_updated', '{{%bb_template}}', ['updated_at']);
        $this->createIndex('idx_template_owner_updated', '{{%bb_template}}', ['owner_user_id', 'updated_at']);
        $this->addForeignKey('fk_template_owner', '{{%bb_template}}', 'owner_user_id', '{{%user}}', 'id', 'SET NULL', 'RESTRICT');

        $this->createTable('{{%bb_bullet}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(120)->notNull(),
            'bullet_type' => "ENUM('habit','feeling','finance','goal') NOT NULL",
            'input_type' => "ENUM('binary','scale','stars','numeric','text') NOT NULL",
            'scale_min' => $this->integer()->null(),
            'scale_max' => $this->integer()->null(),
            'scale_labels' => $this->json()->null(),
            'icon' => $this->string(64)->null(),
            'color' => $this->string(16)->null(),
            'weight' => $this->decimal(8, 3)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('idx_bullet_user', '{{%bb_bullet}}', ['user_id']);
        $this->createIndex('idx_bullet_updated', '{{%bb_bullet}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_bullet_user', '{{%bb_bullet}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_template_bullet}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'template_id' => $this->bigInteger()->unsigned()->notNull(),
            'bullet_id' => $this->bigInteger()->unsigned()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_default_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('uq_template_bullet', '{{%bb_template_bullet}}', ['template_id', 'bullet_id'], true);
        $this->createIndex('idx_tb_template', '{{%bb_template_bullet}}', ['template_id']);
        $this->createIndex('idx_tb_updated', '{{%bb_template_bullet}}', ['updated_at']);
        $this->addForeignKey('fk_tb_template', '{{%bb_template_bullet}}', 'template_id', '{{%bb_template}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_tb_bullet', '{{%bb_template_bullet}}', 'bullet_id', '{{%bb_bullet}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_bullet_entry}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'bullet_id' => $this->bigInteger()->unsigned()->notNull(),
            'entry_date' => $this->date()->notNull(),
            'value_int' => $this->bigInteger()->null(),
            'value_decimal' => $this->decimal(14, 4)->null(),
            'value_text' => $this->string(1000)->null(),
            'note' => $this->string(1000)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('uq_entry_unique', '{{%bb_bullet_entry}}', ['user_id', 'bullet_id', 'entry_date'], true);
        $this->createIndex('idx_entry_user_date', '{{%bb_bullet_entry}}', ['user_id', 'entry_date']);
        $this->createIndex('idx_entry_updated', '{{%bb_bullet_entry}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_entry_user', '{{%bb_bullet_entry}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_entry_bullet', '{{%bb_bullet_entry}}', 'bullet_id', '{{%bb_bullet}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_project}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(160)->notNull(),
            'description' => $this->string(1000)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('idx_project_user', '{{%bb_project}}', ['user_id']);
        $this->createIndex('idx_project_updated', '{{%bb_project}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_project_user', '{{%bb_project}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_task}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'project_id' => $this->bigInteger()->unsigned()->null(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text()->null(),
            'status' => "ENUM('inbox','todo','doing','done','archived') NOT NULL DEFAULT 'inbox'",
            'priority' => "ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium'",
            'due_at' => $this->dateTime(6)->null(),
            'completed_at' => $this->dateTime(6)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('idx_task_user', '{{%bb_task}}', ['user_id']);
        $this->createIndex('idx_task_due', '{{%bb_task}}', ['user_id', 'due_at']);
        $this->createIndex('idx_task_updated', '{{%bb_task}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_task_user', '{{%bb_task}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_task_project', '{{%bb_task}}', 'project_id', '{{%bb_project}}', 'id', 'SET NULL', 'RESTRICT');

        $this->createTable('{{%bb_label}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(80)->notNull(),
            'color' => $this->string(16)->null(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('uq_label_user_name', '{{%bb_label}}', ['user_id', 'name'], true);
        $this->createIndex('idx_label_updated', '{{%bb_label}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_label_user', '{{%bb_label}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_task_label}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'task_id' => $this->bigInteger()->unsigned()->notNull(),
            'label_id' => $this->bigInteger()->unsigned()->notNull(),
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('uq_task_label', '{{%bb_task_label}}', ['task_id', 'label_id'], true);
        $this->createIndex('idx_tl_task', '{{%bb_task_label}}', ['task_id']);
        $this->createIndex('idx_tl_updated', '{{%bb_task_label}}', ['updated_at']);
        $this->addForeignKey('fk_tl_task', '{{%bb_task_label}}', 'task_id', '{{%bb_task}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_tl_label', '{{%bb_task_label}}', 'label_id', '{{%bb_label}}', 'id', 'CASCADE', 'RESTRICT');

        $this->createTable('{{%bb_reminder}}', [
            'id' => $this->bigPrimaryKey()->unsigned(),
            'user_id' => $this->integer()->notNull(),
            'device_id' => $this->bigInteger()->unsigned()->null(),
            'kind' => "ENUM('TASK','DAILY_CHECKIN','WEEKLY_SUMMARY','BULLET') NOT NULL",
            'entity_id' => $this->bigInteger()->unsigned()->null(),
            'channel' => "ENUM('push','email') NOT NULL DEFAULT 'push'",
            'fire_at' => $this->dateTime(6)->null(),
            'cron_expr' => $this->string(64)->null(),
            'timezone' => $this->string(64)->null(),
            'payload' => $this->json()->null(),
            'status' => "ENUM('scheduled','queued','sent','canceled','failed') NOT NULL DEFAULT 'scheduled'",
            'created_at' => $this->dateTime(6)->notNull(),
            'updated_at' => $this->dateTime(6)->notNull(),
            'deleted_at' => $this->dateTime(6)->null(),
        ], $tableOptions);
        $this->createIndex('idx_reminder_due', '{{%bb_reminder}}', ['status', 'fire_at']);
        $this->createIndex('idx_reminder_user_updated', '{{%bb_reminder}}', ['user_id', 'updated_at']);
        $this->addForeignKey('fk_reminder_user', '{{%bb_reminder}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'RESTRICT');
        $this->addForeignKey('fk_reminder_device', '{{%bb_reminder}}', 'device_id', '{{%bb_device}}', 'id', 'SET NULL', 'RESTRICT');
    }

    public function safeDown()
    {
        $this->dropTable('{{%bb_reminder}}');
        $this->dropTable('{{%bb_task_label}}');
        $this->dropTable('{{%bb_label}}');
        $this->dropTable('{{%bb_task}}');
        $this->dropTable('{{%bb_project}}');
        $this->dropTable('{{%bb_bullet_entry}}');
        $this->dropTable('{{%bb_template_bullet}}');
        $this->dropTable('{{%bb_bullet}}');
        $this->dropTable('{{%bb_template}}');
        $this->dropTable('{{%user_access_token}}');
        $this->dropTable('{{%bb_device}}');
    }
}
