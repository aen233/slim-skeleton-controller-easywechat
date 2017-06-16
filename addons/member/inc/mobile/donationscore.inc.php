<?php
defined('IN_IA') or exit('Access Denied');

global $_W,$_GPC;

$uid = mc_openid2uid($_W['openid']);

if (empty($uid)) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，您未绑定微会员，不能使用此功能！')));
    exit();
}

if ($_W['ispost'] && $_W['isajax']) {
    $realName = pdo_fetchcolumn('select realname from ims_mc_members where uid = :uid',array('uid'=>$_GPC['uid']));
    $data = array(
        'uid'         => $_GPC['uid'],
        'realname'    => $realName,
        'openid'      => $_W['openid'],
        'custid'      => $_GPC['custid'],
        'cardsn'      => $_GPC['cardsn'],
        'score'       => $_GPC['score'],
        'status'      => 0,
        'create_time' => time()
    );

    $result = pdo_insert('mc_donation',$data);

    $id = pdo_insertid();

    if ($id) {
        echo json_encode(array('status'=>1,'id'=>$id));
    } else {
        echo json_encode(array('status'=>0,'msg'=>'系统错误，请稍后再试'));
    }
} else {

    $acc = WeAccount::create(4);
    $fan = $acc->fansQueryInfo($_W['openid'], true);
    pdo_update('mc_mapping_fans',array('tag'=>iserializer($fan)), array('openid' => $_W['openid']));

    $fansInfo = mc_fansinfo($uid);


    $card = pdo_fetch('select custid,cardsn from ims_mc_card_members where uid = :uid',array('uid'=>$uid));
    //接口测试7 inc old
        // $score = $this->soapLink()->getCustomerScore($card['custid']);
    //接口测试7 inc new
    $scoreParam=array(
            'custId'=>$card['custid'],
    );
    $scoreArr=$this->apiGetCustScore($scoreParam);
    $score=$scoreArr['data']['data']['scoreList'];
    
    $score = empty($score) ? 0 : $score;
    include $this->template('member/donationScore');
}