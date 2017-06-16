<?php
header('content-type:application/json;charset=utf8');

defined('IN_IA') or exit('Access Denied');

global $_W,$_GPC;


if (!empty($_GPC['openid']) && !empty($_GPC['cardSn'])) {
    $cardInfo = pdo_fetch('select brand,price from card_card_card where password = :password',array('password'=>$_GPC['cardSn']));

    $acc = WeAccount::create(4);

    $temData = array(
        'first'    => array(
            'value' => "您好！您已成功使用 {$cardInfo['brand']} 专柜 {$cardInfo['price']} 元电子券一张。\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => '电子券',
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => $_GPC['cardSn'],
            'color' => '#69008C'
        ),
        'keyword3' => array(
            'value' => date('Y-m-d H:i')."\n",
            'color' => '#69008C'
        ),
        'remark'   => array(
            'value' => "如有疑问，请拨打客服电话：029-86300000",
            'color' => '#000000'
        ),
    );
    $result = $acc->sendTplNotice($_GPC['openid'],'KR7kb8qeLTYKr8sVzIcSJ3NSJAqk4U4oZiEVCUKDx68',$temData,'','#FF683F');
    if ($result) {
        echo json_encode(array('status'=>1,'msg'=>'发送模板消息成功'));
    } else {
        echo json_encode(array('status'=>0,'msg'=>'发送模板消息失败'));
    }

} else {
    echo json_encode(array('status'=>0,'msg'=>'您输入的信息不完整，请核对后使用'));
}