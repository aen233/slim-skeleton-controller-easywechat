<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

$telArr = "13679115567,18392111823,18681979977,13891869221,13891967699,18629337379,13389202208,13700292788,13709129886,18591880643,13186184263,18706800800,15289369828,13519191937,18192114688,13891899150,13609102162,13572089559,15114888189,18009110096,13679116058,13259791666,18629550473,13028516777,13759974316,18681829587,15829532168,13891815522,13319200490,13299176895,13474224605,18729008883";
//$telArr = "13186184263,18729008883";
$uid = mc_openid2uid($_W['openid']);
$fansInfo = mc_fansinfo($uid);
$sql = "SELECT a.openid,b.realname,c.cardsn FROM ims_mc_mapping_fans as a,ims_mc_members as b,ims_mc_card_members as c where a.uid = b.uid and c.uid = a.uid and b.mobile in (".$telArr.") ";

$member = pdo_fetchAll($sql);
$i = 1;
foreach($member as $k => $v){
    $acc = WeAccount::create(4);
    $temData = array(
        'first'    => array(
            'value' => "尊敬的 {$v['realname']}，恭喜您成功升级为VVVIP会员\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => $v['cardsn'],
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => "2016-06-30\n",
            'color' => '#69008C'
        ),

        'remark'   => array(
            'value' => "点击查看高级会员(VVVIP)礼遇，详询：029-86300000",
            'color' => '#000000'
        ),
    );
    $result = $acc->sendTplNotice($v['openid'], '_Nk-69mZav8OlH0UAkpbuYO7Cl4aW-ao3uDl2C1jk-4', $temData, $_W['siteroot'].'app/index.php?i=4&c=entry&do=Rights&m=member', '#FF683F');
    if($result !== false){
        $tmpStr .= $i." ".date("Y-m-d H:i:s")."   ".$v['cardsn']."  ".$v['realname']."   推送成功\r\n";
    }else{
        $tmpStr .= $i." ".date("Y-m-d H:i:s")."   ".$v['cardsn']."  ".$v['realname']."   推送失败\r\n";
    }
    $i ++;
}
echo $tmpStr;
file_put_contents("../logs/vvvipSend/sendlogs.txt", file_get_contents("../logs/vvvipSend/sendlogs.txt").$tmpStr);

