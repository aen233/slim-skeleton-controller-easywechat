<?php
global $_W,$_GPC;

if ($_W['fans']['follow'] != 1) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>'对不起，您未关注赛格国际官方微信，请关注后再操作！')));
    exit();
}

// 接收人是否绑定微会员

$uid = mc_openid2uid($_W['openid']);

if (empty($uid)) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，您未绑定微会员，不能领取此积分！','url'=>$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1')));
    exit();
}

$id = $_GPC['id'];

// 转赠信息

$donation = pdo_fetch('select * from ims_mc_donation where id = :id and  status = 0',array('id'=>$id));

if ($donation['uid'] == $uid) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，请等待对方领取！')));
    exit();
}

if (empty($donation)) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'v','title'=>'信息提示','msg'=>'积分红包已领取过了！')));
    exit();
}

// 发起人剩余积分

//接口测试4 inc old
    //$founderScore = $this->soapLink()->getCustomerScore($donation['custid']);
//接口测试4 inc new
    $scoreParam=array(
    'custId'=>$donation['custid'],
    );

    $scoreArr=$this->apiGetCustScore($scoreParam);
    $founderScore=$scoreArr['data']['data']['scoreList'];

if ($donation['score'] > $founderScore) {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，对方积分不足！')));
    exit();
}


// 接收人信息

$receiveName = pdo_fetch('select realname,nickname from ims_mc_members where uid = :uid',array('uid'=>$uid));

$receiveCard = pdo_fetch('select custid,cardsn from ims_mc_card_members where uid = :uid',array('uid'=>$uid));

// setCustomerScore

// 扣除转赠人积分

//接口测试5 inc old
    //$changeFounder = $this->soapLink()->setCustomerScore($donation['cardsn'],-$donation['score']);
//接口测试5 inc new
    $setSubScoreParam=array(
        'cardId'=>$donation['cardsn'],
        'scoreNum'=>-$donation['score'],
    );
    $setScoreArr= $this->apiSetCustScore($setSubScoreParam);
    //如果返回值data不为空
    $changeFounder=$setScoreArr['data'];
if ($changeFounder) {
    $acc = WeAccount::create(4);

    $temData = array(
        'first'    => array(
            'value' => "您的积分变动详情如下：\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => -$donation['score'],
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => date('Y年m月d日 H:i'),
            'color' => '#69008C'
        ),
        'keyword3' => array(
            'value' => '您转赠的 '.$donation['score'].' 积分'.$receiveName['realname'].'('.$receiveName['nickname'].')'.'已收到',
            'color' => '#69008C'
        ),
        'remark'   => array(
            'value' => "如有疑问，请拨打客服电话：029-86300000",
            'color' => '#000000'
        ),
    );
    $aa = $acc->sendTplNotice($donation['openid'],'q21Hgsbmdd6RRD227YOPLxyLAip2XUps2mqdI4usv1o',$temData,'','#FF683F');
    //接口测试6 inc old
        //$changeReceive = $this->soapLink()->setCustomerScore($receiveCard['cardsn'],$donation['score']);
    //接口测试6 inc new
    $setAddScoreParme=array(
        'cardId'=>$receiveCard['cardsn'],
        'scoreNum'=>$donation['score'],
    );
    $setAddScoreArr= $this->apiSetCustScore($setAddScoreParme);

    $receiveData = array(
        'first'    => array(
            'value' => "您的积分变动详情如下：\n",
            'color' => '#000000'
        ),
        'keyword1' => array(
            'value' => $donation['score'],
            'color' => '#69008C'
        ),
        'keyword2' => array(
            'value' => date('Y年m月d日 H:i'),
            'color' => '#69008C'
        ),
        'keyword3' => array(
            'value' => '您已收到'.$donation['realname'].'转赠的'.$donation['score'].'积分',
            'color' => '#69008C'
        ),
        'remark'   => array(
            'value' => "如有疑问，请拨打客服电话：029-86300000",
            'color' => '#000000'
        ),
    );
    $aa = $acc->sendTplNotice($_W['openid'],'q21Hgsbmdd6RRD227YOPLxyLAip2XUps2mqdI4usv1o',$receiveData,'','#FF683F');

    $data = array(
        'donation_id' => $id,
        'founder_uid' => $donation['uid'],
        'founder_name' => $donation['realname'],
        'receive_uid' => $uid,
        'receive_name' => $receiveName,
        'score' => $donation['score'],
        'create_time' => time()
    );

    $add = pdo_insert('mc_donation_log',$data);
    pdo_update('mc_donation',array('status'=>1),array('id'=>$id));
    $founderFansInfo = mc_fansinfo($donation['uid']);
    $receiveFansInfo = mc_fansinfo($uid);

    include $this->template('member/donationReceive');

} else {
    header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，转赠积分失败！')));
    exit();
}


