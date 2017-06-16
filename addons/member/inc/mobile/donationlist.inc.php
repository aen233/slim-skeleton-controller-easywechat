<?php
global $_W;

$uid = mc_openid2uid($_W['openid']);

if (empty($uid)) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，您未绑定微会员！')));
    exit();
}

$founderList = pdo_fetchall('select * from ims_mc_donation_log where founder_uid = :uid',array('uid'=>$uid));
$receiveList = pdo_fetchall('select * from ims_mc_donation_log where receive_uid = :uid',array('uid'=>$uid));

$custId = pdo_fetchcolumn('select custid from ims_mc_card_members where uid = :uid',array('uid'=>$uid));

//接口测试3 inc old
    //$score = $this->soapLink()->getCustomerScore($custId);
//接口测试3 inc new
    $scoreParam=array(
        'custId'=>$custId,
    );
    $scoreArr=$this->apiGetCustScore($scoreParam);
    $score=$scoreArr['data']['data']['scoreList'];
    $score = empty($score) ? 0 : $score;

include $this->template('member/donationList');