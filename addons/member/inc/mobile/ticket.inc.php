<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

$source = $_GPC['source'] ;
if ($source == 'offline') {

    if ($_W['fans']['follow']) {
        $isSub = 1;
        $openid = $_W['openid'];
        $uid    = mc_openid2uid($openid);

        if (!empty($uid)) {
            $bind = 1;
            $cardId = pdo_fetch('select cardsn,custid from ims_mc_card_members where uid = :uid', array('uid'=>$uid));

            //接口测试11 old
                //$getScore = $this->soapLink()->getCustomerScore($cardId['custid']);
            //接口测试11 new
            $scoreParam=array(
                'custId'=>$cardId['custid'],
            );
            $getScore=$scoreArr['data']['data']['scoreList'];

            $isGet = pdo_fetch('select * from ims_mc_ticket where openid = :openid', array('openid' => $openid));

            if (!empty($getScore) ) {
                $score = 1;
                if (empty($isGet)) {


                    //接口测试12 old
                        //$setScore = $this->soapLink()->setCustomerScore($cardId['cardsn'], -1);
                    //接口测试12 new
                    $scoreParam=array(
                        'cardId'=>$cardId['cardsn'],
                        'scoreNum'=>-1,
                    );
                    $scoreArr=$this->apiSetCustScore($scoreParam);

                    pdo_insert('mc_ticket', array('openid' => $openid, 'source' => 1, 'created_at' => TIMESTAMP));
                    $status = 1;
                } else {
                    $time = date('Y-m-d H:i', $isGet['created_at']);
                }
            }

        }
    }


	include $this->template('member/ticket');
}

if($source == 'online'){

	if($_W['ispost'] && $_W['isajax']){

		if(empty($_W['fans']['follow'])){
			echo json_encode(array('status' => 0,'msg'    => '对不起，请您先关注赛格国际购物中心微信！'));
			exit();
		}
		$openid = $_W['openid'];
		$uid    = mc_openid2uid($openid);

		if(empty($uid)){
			echo json_encode(array('status' => 0,'msg' => '对不起，请您绑定微会员后领取！'));
			exit();
		}
		$cardId = pdo_fetch('select cardsn,custid from ims_mc_card_members where uid = :uid',array('uid' => $uid));
        //接口测试12 old
		    //$getScore = $this->soapLink()->getCustomerScore($cardId['custid']);
        ///接口测试12 new
        $scoreParam=array(
            'custId'=>$cardId['custid'],
        );
        $scoreArr=$this->apiGetCustScore($scoreParam);


        $getScore=$scoreArr['data']['data']['scoreList'];

		if (empty($getScore)) {
			echo json_encode(array('status' => 0,'msg' => '对不起，您的可用积分不足！'));
			exit();
		}
		$isGet = pdo_fetch('select openid from ims_mc_ticket where openid = :openid',array('openid' => $openid));

		if (!empty($isGet)) {
			echo json_encode(array('status' => 0,'msg' => '对不起，您已经领取过！每人只限一次'));
			exit();
		}

		$ticketCode = pdo_fetchcolumn('select code from ims_mc_ticket_code where status = 0');
		if (empty($ticketCode)) {
			echo json_encode(array('status' => 0,'msg' => '对不起，电影票已抢完！'));
			exit();
		}
        //接口测试13 old
		    //$setScore = $this->soapLink()->setCustomerScore($cardId['cardsn'],-1);
		//接口测试13 new

        $scoreParam=array(
            'cardId'=>$cardId['cardsn'],
            'scoreNum'=>-1,
        );
        $scoreArr=$this->apiSetCustScore($scoreParam);

		if (empty($setScore)) {
			echo json_encode(array('status' => 0,'msg' => '网络有点不给力，请稍后再试一下噢！'));
			exit();
		}

		pdo_update('mc_ticket_code', array('status'=>1,'updated_at'=>TIMESTAMP), array('code'=>$ticketCode));

		pdo_insert('mc_ticket', array('openid' => $openid, 'source' => 2, 'created_at' => TIMESTAMP,'code'=>$ticketCode));

		echo json_encode(array('status' => 1,'msg' => '领取成功！'));



	} else {
		$openid = $_W['openid'];
		$data = pdo_fetchcolumn('select code from ims_mc_ticket where openid = :openid',array('openid' => $openid));
		if (!empty($data)) {
			header('Location: '.$this->createMobileUrl('ticket',array('source'=>'getCode'),true));
			exit();
		}

        include $this->template('member/ticket_online');


	}
}

if ($source == 'getCode') {
	$openid = $_W['openid'];
	$data = pdo_fetch('select * from ims_mc_ticket where openid = :openid',array('openid' => $openid));
    if (!empty($data)) {
        include $this->template('member/ticket_getCode');
    } else {
        header('Location: '.$this->createMobileUrl('ticket',array('source'=>'online'),true));
        exit();
    }

}

