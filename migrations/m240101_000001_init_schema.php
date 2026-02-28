<?php

use yii\db\Migration;

class m240101_000001_init_schema extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%users}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(120)->notNull(),
            'email' => $this->string(190)->notNull()->unique(),
            'password_hash' => $this->string()->notNull(),
            'auth_token' => $this->string(255)->null()->unique(),
            'auth_token_expires_at' => $this->dateTime()->null(),
            'jira_base_url' => $this->string(255)->null(),
            'jira_email' => $this->string(190)->null(),
            'jira_api_token' => $this->string(255)->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createTable('{{%projects}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'title' => $this->string(255)->notNull(),
            'status' => $this->string(80)->notNull(),
            'description' => $this->text()->null(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey(
            'fk-projects-user_id-users-id',
            '{{%projects}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk-projects-user_id-users-id', '{{%projects}}');
        $this->dropTable('{{%projects}}');
        $this->dropTable('{{%users}}');
    }
}
