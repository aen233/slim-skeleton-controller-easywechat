<?php


return [

    'settings'=>[
        'httpVersion' => '1.1',
        'responseChunkSize' => 4096,
        'outputBuffering' => 'append',
        'determineRouteBeforeAppMiddleware' => false,
        'displayErrorDetails' => true,
        'addContentLengthHeader' => true,
        'routerCacheFile' => false,

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Renderer settings
        'view' => [
            'twig_template_path' => __DIR__ . '/../templates/views',
            'twig_cache_path'=>__DIR__ . '/../templates/cache',
        ],

         //Monolog settings
        'logger' => [
            'name' => 'slim-test',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        'db'=>[
            'DSN'=>'mysql:host=localhost;dbname=_wx1_cnsaga',
            'user'=>'root',
            'pwd'=>'',
        ],


        //EasyWechat
        'wechat'=>[

            'debug'  => true,

            'app_id'  => 'wxd1df',                      // AppID
            'secret'  => 'f5ac998700a488cd840',        // AppSecret
            'token'   => 'testsaga',                             // Token
            'aes_key' => 'XPYf4mO46joirvJmvPZ2fpljkN',
            'log' => [
                'level'      => 'debug',
                'permission' => 0777,
                'file'       => '/home/phper/wechat.log',
            ],


            /**
             * OAuth 配置
             *
             * scopes：公众平台（snsapi_userinfo / snsapi_base），开放平台：snsapi_login
             * callback：OAuth授权完成后的回调页地址
             */
            'oauth' => [
                'scopes'   => ['snsapi_userinfo'],
                'callback' => '/examples/oauth_callback.php',
            ],


            /**
             * 微信支付
             */
            'payment' => [
                'merchant_id'        => 'your-mch-id',
                'key'                => 'key-for-signature',
                'cert_path'          => 'path/to/your/cert.pem', // XXX: 绝对路径！！！！
                'key_path'           => 'path/to/your/key',      // XXX: 绝对路径！！！！
                // 'device_info'     => '013467007045764',
                // 'sub_app_id'      => '',
                // 'sub_merchant_id' => '',
                // ...
            ],

            'guzzle' => [
                'timeout' => 3.0, // 超时时间（秒）
                //'verify' => false, // 关掉 SSL 认证（强烈不建议！！！）
            ],
        ]
    ]
];