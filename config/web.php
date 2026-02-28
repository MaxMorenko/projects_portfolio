<?php

return [
    'id' => 'portfolio-app',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@webroot' => dirname(__DIR__) . '/web',
        '@web' => '/',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'replace-this-key',
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/me' => 'api/me',
                'GET api/projects' => 'api/projects',
                'POST api/projects' => 'api/create-project',
                'POST api/auth/register' => 'api/register',
                'POST api/auth/login' => 'api/login',
                'PUT api/integrations/jira' => 'api/save-jira',
                '' => 'site/index',
                '<path:[^\\?]*>' => 'site/index',
            ],
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'errorHandler' => [
            'errorAction' => null,
        ],
        'response' => [
            'formatters' => [
                yii\web\Response::FORMAT_JSON => [
                    'class' => yii\web\JsonResponseFormatter::class,
                    'prettyPrint' => false,
                    'encodeOptions' => JSON_UNESCAPED_UNICODE,
                ],
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
];
