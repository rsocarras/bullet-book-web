<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'backend-bullet',
    'name' => 'backend-bullet',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'modules' => [
        'user' => [
            'class' => Da\User\Module::class,
            'classMap' => [
                'User' => app\models\User::class,
            ],
        ],
        'api' => [
            'class' => app\modules\api\Module::class,
            'modules' => [
                'v1' => [
                    'class' => app\modules\api\v1\Module::class,
                ],
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'Opf_a95wmpJvTSqjAZrtNfk4LNNN7vzD',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
                'text/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'POST api/v1/auth/login' => 'api/v1/auth/login',
                'POST api/v1/auth/logout' => 'api/v1/auth/logout',

                'GET api/v1/bullets' => 'api/v1/bullet/index',
                'POST api/v1/bullets' => 'api/v1/bullet/create',
                'GET api/v1/bullets/<id:\\d+>' => 'api/v1/bullet/view',
                'PUT,PATCH api/v1/bullets/<id:\\d+>' => 'api/v1/bullet/update',
                'DELETE api/v1/bullets/<id:\\d+>' => 'api/v1/bullet/delete',

                'GET api/v1/templates' => 'api/v1/template/index',
                'POST api/v1/templates' => 'api/v1/template/create',
                'GET api/v1/templates/<id:\\d+>' => 'api/v1/template/view',
                'PUT,PATCH api/v1/templates/<id:\\d+>' => 'api/v1/template/update',
                'DELETE api/v1/templates/<id:\\d+>' => 'api/v1/template/delete',
                'POST api/v1/user/setup' => 'api/v1/user/setup',

                'POST api/v1/entries' => 'api/v1/entry/create',
                'GET api/v1/entries' => 'api/v1/entry/index',

                'GET api/v1/tasks' => 'api/v1/task/index',
                'POST api/v1/tasks' => 'api/v1/task/create',
                'GET api/v1/tasks/<id:\\d+>' => 'api/v1/task/view',
                'PUT,PATCH api/v1/tasks/<id:\\d+>' => 'api/v1/task/update',
                'DELETE api/v1/tasks/<id:\\d+>' => 'api/v1/task/delete',

                'GET api/v1/sync/pull' => 'api/v1/sync/pull',
                'POST api/v1/sync/push' => 'api/v1/sync/push',

                'GET api/v1/stats/heatmap' => 'api/v1/stats/heatmap',
            ],
        ],
        'queue' => [
            'class' => yii\queue\amqp_interop\Queue::class,
            'host' => getenv('RABBITMQ_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('RABBITMQ_PORT') ?: 5672),
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'queueName' => getenv('RABBITMQ_QUEUE') ?: 'bullet_book_queue',
            'exchangeName' => getenv('RABBITMQ_EXCHANGE') ?: 'bullet_book_exchange',
            'routingKey' => getenv('RABBITMQ_ROUTING_KEY') ?: 'bullet.book',
            'as log' => yii\queue\LogBehavior::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
