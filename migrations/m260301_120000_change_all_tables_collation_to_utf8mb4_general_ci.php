<?php

use yii\db\Migration;

class m260301_120000_change_all_tables_collation_to_utf8mb4_general_ci extends Migration
{
    public function safeUp()
    {
        if ($this->db->driverName !== 'mysql') {
            return true;
        }

        $schema = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $tables = $this->db->createCommand(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME ASC",
            [':schema' => $schema]
        )->queryColumn();

        foreach ($tables as $table) {
            // Convert table and text-like columns in one operation.
            $this->execute(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
                str_replace('`', '``', $table)
            ));
        }
    }

    public function safeDown()
    {
        if ($this->db->driverName !== 'mysql') {
            return true;
        }

        $schema = $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $tables = $this->db->createCommand(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_TYPE = 'BASE TABLE'
             ORDER BY TABLE_NAME ASC",
            [':schema' => $schema]
        )->queryColumn();

        foreach ($tables as $table) {
            $this->execute(sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '``', $table)
            ));
        }
    }
}

