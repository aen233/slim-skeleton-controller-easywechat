<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};


$container['view'] = function ($c) {
    $settings = $c->get('settings')['view'];
    $view = new \Slim\Views\Twig($settings['twig_template_path'], [
//        'cache' =>$settings['twig_cache_path']
        'cache' =>false
    ]);

    $view->addExtension(new \Slim\Views\TwigExtension(
        $c->router,
        $c->request->getUri()
    ));
    return $view;
};

$container['wechat'] = function($c){
    $settings = $c->get('settings')['wechat'];
    return new EasyWeChat\Foundation\Application($settings);
};

$container['db']=function($c){

    require __DIR__ . '/../vendor/NotORM.php';

    $arg = $c->get('settings')['db'];
    $pdo = new PDO($arg['DSN'],$arg['user'],$arg['pwd']);
    $pdo->exec('set names utf8');//不加会乱码
    $db = new NotORM($pdo);
    $db->debug = true;
    return $db;
};






