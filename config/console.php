<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'backend-bullet-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'queue'],
    'controllerNamespace' => 'app\commands',
    'modules' => [
        'user' => [
            'class' => Da\User\Module::class,
            'classMap' => [
                'User' => app\models\User::class,
            ],
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
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
    'controllerMap' => [
        'queue' => [
            'class' => yii\queue\console\Controller::class,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
