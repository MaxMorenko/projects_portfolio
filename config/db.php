<?php

return [
    'class' => yii\db\Connection::class,
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST') ?: 'db',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'portfolio'
    ),
    'username' => getenv('DB_USER') ?: 'portfolio',
    'password' => getenv('DB_PASSWORD') ?: 'portfolio',
    'charset' => 'utf8mb4',
];
