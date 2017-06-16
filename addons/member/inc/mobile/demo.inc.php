<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
global $_W;
set_time_limit(0);

echo '<pre>';
print_r($_W['fans']['follow']);
exit;
$barcode = array(
    'expire_seconds' => '',
    'action_name' => 'QR_LIMIT_SCENE',
    'action_info' => array(
        'scene' => array('scene_id' => ''),
    ),
);
$uniacccount = WeAccount::create(4);

/*for ($i = 2212;$i <= 2221 ;$i++) {
    $shop .= '学生'.$i.',';
}
echo trim($shop,',');*/

/*for ($i = 2223;$i <= 2226 ;$i++) {
    $shop = '学生'.$i;
    $barcode['action_info']['scene']['scene_id'] = $i;
    $result = $uniacccount->barCodeCreateFixed($barcode);

    $data = array(
        'ticket'     => $result['ticket'],
        'url'        => $result['url'],
        'createtime' => TIMESTAMP,
        'uniacid'    => 4,
        'acid'       => 4,
        'qrcid'      => $i,
        'name'       => $shop . '推广',
        'keyword'    => $shop,
        'model'      => 2,
        'status'     => 1,
        'type'       => 'scene',
    );

    pdo_insert('qrcode', $data);
    pdo_insert('mc_spread_shop',array('shopinfo'=>'学生'.$i,'shopname'=>'学生'.$i));
    pdo_insert('rule_keyword',array('rid'=>29,'uniacid'=>4,'module'=>'expand','content'=>'学生'.$i,'type'=>1,'displayorder'=>0,'status'=>1));
}*/

