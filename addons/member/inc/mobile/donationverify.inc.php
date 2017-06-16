<?php
global $_W,$_GPC;

$id = $_GPC['id'];

$donationInfo = pdo_fetch('select * from ims_mc_donation where id = :id',array('id'=>$id));

$fansInfo = mc_fansinfo($donationInfo['uid']);

$custId =  pdo_fetchcolumn('select custid from ims_mc_card_members where uid = :uid',array('uid'=>$donationInfo['uid']));
//接口测试8 inc old
    //$score = $this->soapLink()->getCustomerScore($custId);
//接口测试8 inc new
$scoreParam=array(
    'custId'=>$card['custid'],
);
$scoreArr=$this->apiGetCustScore($scoreParam);
$scoreData=$scoreArr['data']['data'];
$score = empty($scoreData) ? 0 : $scoreData['scoreList'];

include $this->template('member/donationVerify');