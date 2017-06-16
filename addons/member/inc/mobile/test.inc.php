<?php
/*
defined('IN_IA') or exit('Access Denied');
set_time_limit(0);
error_reporting(-1);
global $_W, $_GPC;

$acc = WeAccount::create(4);

$users = pdo_fetchall("SELECT id,openid,`password`,price,brand,start_time,end_time FROM card_card_card WHERE `status` = 2 AND openid <> '' GROUP BY openid ORDER BY id asc LIMIT 1700,200");

foreach ($users as $key => $value) {
    $temData = array(
        'first'    => array(
            'value' => "您好，您有电子券即将到期：\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => '电子券',
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => $value['password'],
            'color' => '#69008C'
        ),
        'keyword3' => array(
            'value' => $value['start_time'],
            'color' => '#69008C'
        ),
        'keyword4' => array(
            'value' => $value['end_time'],
            'color' => '#69008C'
        ),
        'remark'   => array(
            'value' => "如有疑问，客服电话：029-86300000\n\n请您关注电子券的使用日期，至发券专柜使用电子券，无门槛优惠不容错过，赛格期待您的再次光临。\n点击详情查看未使用电子券。",
            'color' => '#000000'
        ),
    );
    $acc->sendTplNotice($value['openid'], '3lJYqySWHIdKmoNWty8n3SxhHxfiSHAj5KWFw0JGmN8', $temData, 'http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=coupons&m=member', '#FF683F');
//    logs('用户： '.$value['openid'],'sendCard');
}*/