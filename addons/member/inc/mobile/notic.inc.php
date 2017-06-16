<?php
defined('IN_IA') or exit('Access Denied');
global $_W, $_GPC;

if (!empty($_GPC['equName']) || !empty($_GPC['equCode']) || !empty($_GPC['errorContent'])) {
    $acc = WeAccount::create(4);

    $equName = urldecode($_GPC['equName']);
    $equCode = urldecode($_GPC['equCode']);
    $errorContent = urldecode($_GPC['errorContent']);

    $temData = array(
        'first'    => array(
            'value' => "机器 {$equName} 出现了故障，内容如下：\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => $equName,
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => $equCode,
            'color' => '#69008C'
        ),
        'keyword3' => array(
            'value' => $errorContent,
            'color' => '#69008C'
        ),
        'keyword4' => array(
            'value' => "无\n",
            'color' => '#69008C'
        ),
        'remark'   => array(
            'value' => "如有疑问，请拨打电话：18691521952",
            'color' => '#000000'
        ),
    );
    $aa      = $acc->sendTplNotice('oUdGzjvzXu14l5M6QbfDV4-mSn_8', 'St1FqO2cTZKL-UlK2Onuwf0KHr-0mrVJaJm3dgPJbDs', $temData, '', '#FF683F');
    echo json_encode(array('msg' => '发送成功', 'status' => 1));
    //$aa      = $acc->sendTplNotice('oUdGzjhRjhuV0PivMKon9WMXu-Yw', 'St1FqO2cTZKL-UlK2Onuwf0KHr-0mrVJaJm3dgPJbDs', $temData, '', '#FF683F');
    //echo json_encode(array('msg' => '发送成功', 'status' => 1));
    exit();
}