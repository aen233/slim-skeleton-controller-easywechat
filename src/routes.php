<?php
// Routes


$app->get('/hello/[{name}]', 'App\Controllers\Index:hello');
$app->get('/','App\Controllers\Home:home');

$app->group('/',function (){
    $this->get('sss','App\Controllers\Index:abc');

    $this->get('user','App\Controllers\Home:users');
    $this->get('check','App\Controllers\Home:ccheck');
    $this->any('admin','App\Controllers\Home:admin');

});
$app->get('/token','App\Controllers\WeChat:getToken');
$app->any('/serve','App\Controllers\WeChat:serve');
$app->any('/list','App\Controllers\WeChat:getuser');
$app->any('/createcard','App\Controllers\WeChat:createcard');
$app->any('/qrcard','App\Controllers\WeChat:WXQR');







