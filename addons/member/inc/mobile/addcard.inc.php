<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

if ($_W['ispost'] && $_W['isajax']) {
    $data = array(
        'price'      => $_GPC['price'],
        'tel'        => $_GPC['tel'],
        'openid'     => $_GPC['openid'],
        'orderid'    => $_GPC['orderid'],
        'c_dept_id'  => $_GPC['c_dept_id'],
        'brand'      => $_GPC['brand'],
        'start_time' => $_GPC['start_time'],
        'end_time'   => $_GPC['end_time'],
        'orderPrice' => $_GPC['orderPrice'],
        'status' => 2,
        'r_way'      => 2
    );
    // 领卡
    $password = pdo_fetchcolumn('select password from card_card_card where status = 1');

    if ($password) {

        $result = pdo_query('update card_card_card set price = :price,tel = :tel,openid = :openid,orderid = :orderid,status = :status,c_dept_id = :c_dept_id,brand = :brand,start_time = :start_time,end_time = :end_time,orderPrice = :orderPrice,r_way = :r_way where password = '.$password,$data);

        if ($result) {
            $acc = WeAccount::create(4);

            $temData = array(
                'first'    => array(
                    'value' => "恭喜您成功领取 {$_GPC['price']} 元 {$_GPC['brand']} 电子券一张。\n",
                    'color' => '#000000'
                ),
                'keyword1' => array(
                    'value' => '电子券',
                    'color' => '#69008C'
                ),
                'keyword2' => array(
                    'value' => $password,
                    'color' => '#69008C'
                ),
                'keyword3' => array(
                    'value' => $_GPC['start_time'],
                    'color' => '#69008C'
                ),
                'keyword4' => array(
                    'value' => "2015-10-31\n",
                    'color' => '#69008C'
                ),
                'remark'   => array(
                    'value' => "如有疑问，请拨打客服电话：029-86300000",
                    'color' => '#000000'
                ),
            );
            $aa = $acc->sendTplNotice($_GPC['openid'],'NeJT0MzlrbnEO9fL0x5zmTXzbUeq5vQCyGCtti_p52E',$temData,$_W['siteroot'].'app/index.php?i=4&c=entry&do=coupons&m=member','#FF683F');
            echo json_encode(array('msg' => '领取成功', 'status' => 1,'password'=>$password));
            exit();
        } else {
            echo json_encode(array('msg' => 'Oh..好像出了点问题，请稍后试一下', 'status' => 0));
            exit();
        }

    } else {
        echo json_encode(array('msg' => '对不起，电子券已领完！', 'status' => 0));
        exit();
    }
}