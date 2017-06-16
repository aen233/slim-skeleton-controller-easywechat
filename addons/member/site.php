<?php

defined('IN_IA') or exit('Access Denied');
/*error_reporting(0);

ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);*/
require 'function.php';

load()->classs('CardApi');

load()->classs('Contract');




class MemberModuleSite extends WeModuleSite
{
	/**
	 * 会员中心页
	 */
	protected $apiHost='http://192.168.0.20/';

    public function doMobileMember()
    {
    	
        global $_W,$_GPC;
        $openid = $_W['openid'];
        $_W['page']['title'] = '我的会员卡';

        $uid = mc_openid2uid($_W['openid']);

        if($uid){
            $contract_id = pdo_fetch("select * from ims_contract_record where uid =".$uid);
            if(empty($contract_id)){
                file_get_contents("http://wx.cnsaga.com/soap/customer/service/restore.php?openid=".$_W['openid']."&uid=".$uid);
            }
        }

	     $fans = $this->commonHeader($uid,$_GPC['tag']);

        if($_GET['test'] == 'test'){
            $uid = 49991;
            $fans = $this->commonHeader($uid,$_GPC['tag']);

            var_dump($fans);

        }
        $fans['cardIdDisplay'] = substr($fans['cardid'],0,4)." ".substr($fans['cardid'],4,4)." ".substr($fans['cardid'],-4);
       
	    $fanInfo = pdo_fetch(" select fanid from ims_mc_mapping_fans where openid = '".$openid."' ");
        
        if($fans['cardType'] == 'VVVIP' && date('Ymd') >= '20160129' ){
        	//高级会员会员卡页
        	include $this->template('member/memberSenior');
        }else{
        	include $this->template('member/member');
        }
		
	
		
		
    }

    /**
     * @TODO 会员信息详细页面
     */
    public function doMobilePersonalInfo()
    {
        global $_W, $_GPC;

        $openid = $_W['openid'];
        //@todo 通过openid ,查询ERP远端信息，如果数据存在，则直接更新数据值，否则的话
        //@todo 插入数据信息
        $uid = mc_openid2uid($openid);

        $basicInfo = pdo_fetch("SELECT `uid`,`realname`,`idcard`,`isValued`,`gender`,`mobile` FROM `ims_mc_members` WHERE `uid`=".$uid);
        $parkScore = pdo_fetch("SELECT `score` FROM `ims_park_member` WHERE `uid`=".$uid);
        //接口测试8 old
//            $soapClient = new CardApi();
//            $custid = $soapClient->getCustomerCustID(array('idNum'=>$basicInfo['idcard']));
//            $cardinfo = $soapClient->getCustomerInfo($custid);
//            $erpScore = $soapClient->getCustomerScore($custid);
        //接口测试8 new
        $infoParam=array(
            'idNum'=>$basicInfo['idcard'],
        );
        $cardinfoArr=$this->apiGetCustInfo($infoParam);
        $cardinfo=$cardinfoArr['data']['info'];
        $custid=$cardinfo['custid'];
        $scoreParam=array(
            'custId'=>$custid,
        );
        $erpScoreArr = $this->apiGetCustScore($scoreParam);
        $erpScore=$erpScoreArr['data']['data']['scoreList'];

        if($erpScore == false){
            $erpScore = 0;
        }

        //@todo ims_mc_card_members    同步更新数据库信息
        $localCardInfo = pdo_fetch("SELECT `cardsn` FROM `ims_mc_card_members` WHERE `uid`=".$uid);
        if(empty($localCardInfo)){
             $insert_Card_Sql = "INSERT INTO `ims_mc_card_members` (`uniacid`,`uid`,`cid`,`custid`,`cardsn`,`status`,`createtime`) VALUES (4,".$uid.",0,'".$custid."', '".$cardinfo['cardId']."', 1, ".time().")";
             pdo_query($insert_Card_Sql);
        }else{
             $update_cardsn_sql = "UPDATE `ims_mc_card_members` SET `cardsn`='".$cardinfo['cardId']."' WHERE `uid`=".$uid;
             pdo_query($update_cardsn_sql);
        }

        //@todo ims_mc_members_score   同步更新数据库信息
        $localScore = pdo_fetch("SELECT `score` FROM `ims_mc_members_score` WHERE `uid`=".$uid);
        if(empty($localScore)){
            $insert_score_sql = "INSERT INTO `ims_mc_members_score` (`uid`,`openid`,`custid`,`cardsn`,`score`,`sync_time`,`create_time`) VALUES (".$uid.",'".$openid."','".$custid."','".$cardinfo['cardid']."',".$erpScore.",".time().",".time().")";

        }else{
            //$update_cardsn_sql = "UPDATE `ims_mc_members_score` SET `score`= ".$erpScore." WHERE `uid`=".$uid;
            //$update_cardsn_sql = "UPDATE `ims_mc_members_score` SET `cardsn`= '".$cardinfo['cardId']."' WHERE `uid`=".$uid;
        }


        $cardNum = $cardinfo['cardId'];
        $label = $cardNum{0};

        $hiddenIdCard = substr($basicInfo['idcard'],0,6);
        $hiddenIdCard .= "********";
        $hiddenIdCard .= substr($basicInfo['idcard'],14,4);

        $parkScore = $parkScore['score'];

        include $this->template('member/memberinfo');

    }


    /**
     * 加载停车扣分记录值，根据 Ajax 程序返回数据数据值
     */
    public function doMobilePapinfo(){
        global $_W,$_GPC;

        if(isset($_GET['page'])){
            $page = $_GET['page'];
        }else{
            $page = 0;
        }
        /**
          * 获取会员停车记录付款信息
          * @var Array   以会员Uid检索停车场付款信息(类型为 微信主动支付, 停车专用积分,
          *  ERP 积分, 微信委托代扣，四种混合类型)
        */
        $start = $page * 10;

        $process = 10;
        $uid = mc_openid2uid($_W['openid']);

        if(empty($uid)){
            header("http://wx.cnsaga.com/app/index.php?i=4&c=entry&eid=1");
            exit();
        }

        // 根据用户上滑动的数据值更改数据查询记录项目
        $paprecord = pdo_fetchall("SELECT `platenumber`,`entrytime`,`leavetime`,`duration`,`amount`,`pay`,`erpscore`,`parkscore`,`create_time`
            FROM `ims_park_logs` WHERE `uid`='".$uid."' ORDER BY `create_time` DESC LIMIT ".$start.",".$process);
        //var_dump($uid);
        for($i = 0; $i < count($paprecord);$i++){
            foreach($paprecord[$i] as $key => $val){
                if ('erpscore' == $key || 'parkscore' == $key) {
                    $paprecord[$i][$key] = intval($val);
                }
            }
        }
        //var_dump($paprecord);
        // 传递停车扣费记录值
        include $this->template('member/paprecord');
        // 没有更多的数据值了
    }

    public function doMobileAsync(){
         global $_W,$_GPC;

        if(isset($_GET['page'])){
            $page = $_GET['page'];
        }else{
            $page = 0;
        }
       /**
          * 获取会员停车记录付款信息
          * @var Array   以会员Uid检索停车场付款信息(类型为 微信主动支付, 停车专用积分,
          *  ERP 积分, 微信委托代扣，四种混合类型)
        */
        $start = $page * 10;

        $process = 10;
        $uid = mc_openid2uid($_W['openid']);
        
        // 根据用户上滑动的数据值更改数据查询记录项目
        $paprecord = pdo_fetchall("SELECT `platenumber`,`entrytime`,`leavetime`,`duration`,`amount`,`pay`,`erpscore`,`parkscore`,`create_time` FROM `ims_park_logs` WHERE `uid`='".$uid."' ORDER BY `create_time` DESC LIMIT ".$start.",".$process);

        //echo $uid;

        if(empty($paprecord)){
              echo "0";
        }

        for($i = 0; $i < count($paprecord); $i++){

            echo "<li class='radbox'>";
            echo        "<dl>";
            echo          "<dt><strong>".$paprecord[$i]['platenumber']."</strong><span>停车时长：<u>".$paprecord[$i]['duration']."</u>小时</span></dt>";
            echo           "<dd>";
            echo           "<span>扣款总额：".$paprecord[$i]['amount']."</span>";
            echo           "<span>微信委托代扣：".$paprecord[$i]['pay']."元</span>";
            echo           "<span>抵扣积分：".intval($paprecord[$i]['erpscore'])."</span>";
            echo           "<span>抵扣停车专用积分：".intval($paprecord[$i]['parkscore'])."</span>";
            echo           "<span>入场时间：".$paprecord[$i]['entrytime']."</span>";
            echo           "<span>离场时间：".$paprecord[$i]['leavetime']."</span>";
            echo           "</dd>";
            echo        "</dl>";
            echo "</li>";
        }
    }

	public function doMobileDcTest(){
		$param=array(
	              'tel'=>18409271456,
	            );
	    $userInfoArr=$this->apiGetCustInfo($param);
		var_dump($userInfoArr);
		
	    pdo_insert('mc_card_members',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'custid'=>$userInfoArr['data']['info']['custId'],'cardsn'=>$userInfoArr['data']['info']['cardId'],'status'=>1,'createtime'=> TIMESTAMP));
	}

    //获取用户基本资料
    public function commonHeader($uid,$tag = 0)
    {
        global $_W;
        $cardId = 0;
        if (!empty($uid)) {
            $memberInfo = mc_fetch($uid,array('mobile','idcard','realname','platenumber','platenumber2',
                'platenumber3','isOn','isValued'));

            /**
             * 未开启积分抵扣，绑定车牌，没有查到会员信息标准！
             */
            if (!empty($payInfo)) {
                $identifier = 1;
            }else{
                $identifier = 0;
            }
            /**
             * 同步会员卡信息
             */
            $custid = pdo_fetchcolumn("select custid from ims_mc_card_members where uid = :uid",array('uid'=>$uid));

            if (empty($custid)) {
                $mobile = mc_fetch($uid,array('mobile'));

                //接口测试1 old
        /*
                $custid = $this->soapLink()->getCustomerCustID(array('tel'=>$memberInfo['mobile']));
                if ($custid) {
                    $userinfo = $this->soapLink()->getCustomerInfo($custid);
                    pdo_insert('mc_card_members',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'custid'=>$userinfo['custId'],'cardsn'=>$userinfo['cardId'],'status'=>1,'createtime'=> TIMESTAMP));
                }
        */
                //接口测试1 new

                $param=array(
                  'tel'=>$mobile,
                );
                $userInfoArr=$this->apiGetCustInfo($param);
                pdo_insert('mc_card_members',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'custid'=>$userInfoArr['data']['info']['custId'],'cardsn'=>$userInfoArr['data']['info']['cardId'],'status'=>1,'createtime'=> TIMESTAMP));

            }else{
            	//老会员更新会员卡号
            	$mobile = mc_fetch($uid,array('mobile'));
                //接口测试2 old
                /*
                $custid = $this->soapLink()->getCustomerCustID(array('tel'=>$memberInfo['mobile']));
                if ($custid) {
               		$userinfo = $this->soapLink()->getCustomerInfo($custid);
                    //if (substr($userinfo['cardId'],0,3) != 101) {
                        pdo_query(" update ims_mc_card_members set cardsn='".$userinfo['cardId']."' where uid = '".$uid."' ");

                        // 高级会员极端异常情况 2
                        if($uid == 712881){
                            pdo_query(" update ims_mc_card_members set cardsn='801000000008' where uid = '".$uid."' ");
                        }
                        // 高级会员极端异常情况 3
                        if($uid == 22349){
                            //$custid = $this->soapLink()->getCustomerCustID(array('idNum'=>$memberInfo['idcard']));
                            pdo_query(" update ims_mc_card_members set cardsn='801000003347' where uid = '".$uid."' ");
                        }
                    //}
                }
                */

                //接口测试2 new
                $param=array(
                    'tel'=>$mobile['mobile'],
                );
                $userInfoArr=$this->apiGetCustInfo($param);



                pdo_query(" update ims_mc_card_members set cardsn='".$userInfoArr['data']['info']['cardId']."' where uid = '".$uid."' ");
                // 高级会员极端异常情况 2
                if($uid == 712881){
                    pdo_query(" update ims_mc_card_members set cardsn='801000000008' where uid = '".$uid."' ");
                }
                // 高级会员极端异常情况 3
                if($uid == 22349){
                    //$custid = $this->soapLink()->getCustomerCustID(array('idNum'=>$memberInfo['idcard']));
                    pdo_query(" update ims_mc_card_members set cardsn='801000003347' where uid = '".$uid."' ");
                }

            }

            $contractInfo = pdo_fetchcolumn("SELECT `contract_id` FROM `ims_contract_record` WHERE `uid`=".$uid);


             $mobile = $memberInfo['mobile'];
             $serial = Contract::entrustwebData($mobile);
             $httprequest = ContractContents::SIGN_CONTRACT_HTTP;
             $serial = $httprequest.'?'.$serial;

            /**
             * 首次绑定送1000积分
             */
            $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid",array('uid'=>$uid));
            if ($count == 0) {
                $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
                $id     = pdo_insertid();
            }

            if (!empty($id)) {
                $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid",array('uid'=>$uid));
                if ($parkCount == 0){
                    pdo_insert('park_member',array('openid'=>$_W['openid'],'uid'=>$uid,'score'=>1000,'mobile'=>$memberInfo['mobile'],'realname'=>$memberInfo['realname'],'create_time' => TIMESTAMP));
                } else {
                    //pdo_query("UPDATE `ims_park_member` SET `score` = score +1000 WHERE `uid` = '{$uid}'");
                }
            }

        	/*
            if (!empty($memberInfo['platenumber']) && $memberInfo['isOn'] == 1) {
                $client      = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');
                $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $memberInfo['platenumber'])));

                if (empty($getRequest1->GetMemberPlateNumberInfoResult)) {
                    $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $memberInfo['platenumber'])));
                }
            }

            if (!empty($memberInfo['platenumber2']) && $memberInfo['isOn'] == 1) {
                $client      = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');
                $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $memberInfo['platenumber2'])));

                if (empty($getRequest1->GetMemberPlateNumberInfoResult)) {
                    $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $memberInfo['platenumber2'])));
                }
            }

            if (!empty($memberInfo['platenumber3']) && $memberInfo['isOn'] == 1) {
                $client      = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');
                $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $memberInfo['platenumber3'])));

                if (empty($getRequest1->GetMemberPlateNumberInfoResult)) {
                    $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $memberInfo['platenumber3'])));
                }
            }
            */

            // 通过 UID 获取会员及卡相关信息
            $sql      = 'SELECT m.realname,m.platenumber,c.custid,c.cardsn FROM ' . tablename('mc_members') . 'as m ,' . tablename('mc_card_members') . ' AS c WHERE m.uid = c.uid AND m.uid = ' . $uid;
            $card     = pdo_fetch($sql);
            $cardId   = $card['cardsn'];
            $name     = $card['realname'];

            // 获取会员积分及会员卡信息

            //接口测试3 old
    /*
            $score    = $this->soapLink()->getCustomerScore($card['custid']);
            $cardInfo = $this->soapLink()->getCustomerCard($card['custid']);
    */
            //接口测试3 new

            $scoreParam=array(
                'custId'=>$card['custid'],
            );
            $scoreArr=$this->apiGetCustScore($scoreParam);;

            $score=$scoreArr['data']['data']['scoreList'];

            $infoParam=array(
                'custId'=>$card['custid'],
            );
            $custInfoArr=$this->apiGetCustInfo($infoParam);;
            $cardId=$custInfoArr['data']['info']['cardId'];

            $cardTypeName=substr($cardId,0,1);

            if($cardTypeName==6){
                $cardType='VIP';
            }elseif($cardTypeName==7){
                $cardType='VVIP';
            }elseif($cardTypeName==8){
                $cardType='VVVIP';
            }

//            $cardType = ($cardInfo['cardTypeName']) ? $cardInfo['cardTypeName'] : 'VIP';
            // 获取会员停车专用积分
            $parkScore = pdo_fetchcolumn("select score from ims_park_member where uid = :uid",array('uid'=>$uid));
            $erpScore = empty($score) ? 0 : $score;
            $scores = $erpScore + $parkScore;
        }

        $s = array('serial'=>$serial, 'uid' => $uid, 'name' => $name, 'score' => $scores,'erpScore'=>$erpScore, 'cardid' => $cardId, 'cardType' => $cardType,'parkScore'=>$parkScore,'isValued'=>$memberInfo['isValued'], 'plateNumber'=>$card['platenumber']);
        //print_r($s);
        return $s;
    }


    /**
     * 戴晨接口测试
     */

    public function doMobileApiDc()
    {
        $infoParam=array(
            'idNum'=>610429199503101719,
        );
        $custInfoArr=$this->apiGetCustInfo($infoParam);
//        if($custInfoArr['data']['code']==11){
//            echo json_encode(array('status' => 0, 'msg' => '获取会员卡信息失败')); exit();
//        }
//        if ($custInfoArr['data']['info']['mobile'] != $mobile) {
//            echo json_encode(array('status' => 0, 'msg' => '该证件号已是会员，您输入的手机号码与原会员手机号码记录不匹配。为了您会员帐户的安全，请携带有效证件至一楼服务台进行办理')); exit();
//        }
//        $idNumTag = true;
        var_dump($custInfoArr);
    }


    /**
     * 我的会员特权
     */
    public function doMobileMemberPrivilege()
    {
        global $_W, $_GPC;

        if (mc_openid2uid($_W['openid'])) {
            $title = '我的会员特权';
            include $this->template('member/memberPrivilege');
        } else {
            $msg = '会员特权需要绑定资料：';
            include $this->template('member/notice');
        }
    }

    public function doMobileRegistValuedMember()
    {
        global $_W,$_GPC;

        $uid = $_GPC['uid'];

        $changeValued = pdo_update('mc_members',array('isValued'=>1),array('uid'=>$uid));

        if ($changeValued !== false) {
             echo json_encode(array('status'=>1,'msg'=>'您的申请已收到，初步审核合格后，我们会尽快与您联系后续事宜'));
        } else {
            echo json_encode(array('status'=>0,'msg'=>'对不起，申请失败'));
        }
    }


    public function doMobileReApplyinfo()
    {
        global $_W, $_GPC;

        $uid = $_GPC['uid'];

        $reApply = pdo_update('mc_members',array('isValued'=>10),array('uid'=>$uid));

        if ($reApply !== false) {
            echo json_encode(array('status'=>1, 'msg'=>'您的二次申请已收到，初步审核合格后，我们会尽快与您联系后续事宜'));
        } else {
            echo json_encode(array('status'=>0, 'msg'=>'对不起，申请失败'));
        }
    }

    /**
     * 菜单车牌绑定说明
     */
    public function doMobilePark()
    {;
        include $this->template('member/park');
    }

    public function doMobileWxpay(){

    }

    /**
     * 我的优惠券
     */
    public function doMobileCoupons()
    {
        global $_W;
        $_W['page']['title'] = '我的电子券';
//      $coupons             = pdo_fetchall('SELECT id,title,thumb,endtime FROM ' . tablename('activity_exchange'));
        //$coupons = pdo_fetchall('select * from card_card_card where openid = :openid and `status` = 2 and start_time > \'2017-01-31\' ',array('openid'=>$_W['openid']));
        $coupons = pdo_fetchall('select * from `card_card_card` where openid = :openid and `status` = 2 and start_time > \'2017-01-31\' ',array('openid'=>$_W['openid']));

        //var_dump($_W['openid']);

        //var_dump($coupons);
        include $this->template('member/coupons');
    }

    /**
     * 我的优惠券详情页
     */
    public function doMobileCouponInfo()
    {
        global $_W, $_GPC;

        $coupons = pdo_fetch('SELECT id,title,thumb,description,starttime,endtime FROM ' . tablename('activity_exchange') . ' WHERE id = :id', array('id' => $_GPC['couponId']));
        if ($_GPC['couponId'] == 2) {
            $uid = pdo_fetchcolumn('SELECT fanid FROM ' . tablename('mc_mapping_fans') . ' WHERE openid = :openid', array('openid' => $_W['openid']));
            $uid = base64_encode($uid);

            include $this->template('member/card');
        } else {
            include $this->template('member/card');
        }
    }

    public function doMobileScore()
    {
        global $_W,$_GPC;
        if ($uid = mc_openid2uid($_W['openid'])) {
            $fans = $this->commonHeader($uid);
            header('location: '.$_W['siteroot'].'1219/scoreList/?score='.$fans['score'].'&name='.$fans['name'].'&type='.$fans['cardType']);
        } else {
            header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1');
            exit();
        }
    }

    /**
     * 购物记录
     */
    public function doMobileGoods()
    {
        global $_W;
        if ($uid = mc_openid2uid($_W['openid'])) {
            $cardSn = pdo_fetchcolumn('select cardsn from' . tablename('mc_card_members') . ' where uid = :uid', array('uid' => $uid));
            $sales  = $this->soapLink()->getCustomerSale($cardSn);
            include $this->template('member/goods');
        } else {
            header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1');
            exit();
        }
    }

    /**
     * 购物记录
     */
    public function doMobileGoodsTest()
    {
        global $_W; //164
        if ($uid = mc_openid2uid($_W['openid'])) {
            $cardSn = pdo_fetch('select custid,cardsn from' . tablename('mc_card_members') . ' where uid = :uid', array('uid' => 164));
            $sales  = $this->soapLink()->getCustomerSale($cardSn['cardsn']);

            //接口测试7 old
        //         $score    = $this->soapLink()->getCustomerScore($cardSn['custid']);
            //接口测试 7 new

            $scoreParam=array(
                'custId'=>$card['custid'],
            );
            $scoreArr=$this->apiGetCustScore($scoreParam);;
            $score=$scoreArr['data']['data']['scoreList'];

            $data = array();
            foreach ($sales as $key => $value) {
                $data[$value['retailId']]['total'] += $value['money'];
                $data[$value['retailId']]['retailTime'] = substr($value['retailTime'],0,10);
                $data[$value['retailId']][] = $value;

            }
//            echo '<pre>';
//            print_r($data);
            include $this->template('member/goodsTest');
        } else {
            header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1');
            exit();
        }
    }

    public function doMobileGetBill(){
        global $_GPC;
        $billId = $_GPC['billId'];
        $billInfo = $this->soapLink()->getBillInfor($billId);
//        echo '<pre>';
//        print_r($billInfo);
        include $this->template('member/getBill');
    }
    public function doMobileselectPlateNumber(){
    	global $_W, $_GPC;
    	$msg = $_GPC['msg'];
    	include $this->template('member/selectPlateNumber');
    }

	//微信会员卡停车服务入口
    public function doMobileJudgeCustPlate()
    {
        global $_W;
		/*
        $pl_sql="SELECT platenumber FROM ".tablename('mc_members')." WHERE uid='{$uid}'";
        $platenumber=($pl_sql);
        if($platenumber){
            header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=selectPlateNumber&m=member");
        }else{
            header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=ParkPay&m=member");
        }*/
       // 根据UID查找会员停车积分
        $uid 	= mc_openid2uid($_W['openid']);
        $pscore	=pdo_fetch("SELECT score FROM ".tablename('park_member')." WHERE uid='{$uid}'");
        $pscore	=empty($pscore)?'0':$pscore;
        include $this->template('member/parkserver');

    }
    //微信会员卡 会员中心
    public function doMobileMemberCenter(){
    	global $_W,$_GPC;
    	include $this->template('member/memberCenter');
    }
    //微信会员卡 会员中心 千人千面
    public function doMobileMemberFace(){

        global $_W;
        $localId=$_POST['localId'];
       	$serverId=$_POST['serverId'];
        //$serverId='["nP0K_VioiHAimKiazOJdidqBuGZKmxVWTMXeEAAcY4H-yqLRZzCFaVX7Z-ZmVtW9"]';
        $token = $this->doMobileGetToken();
        if($serverId!='') {
            $sql = "INSERT INTO " . tablename('mc_testSeverId') . " (serverId) VALUES" . "('{$serverId}')";
            pdo_query($sql);
            /*$ch = curl_init("http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$token."&media_id=".$serverId);
            $fp = fopen(FCPATH.$targetName, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);*/
            $mediaId=json_decode($serverId,true);
            $str = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=".$token."&media_id=".$mediaId[0];
            $a = file_get_contents($str);
            $filePath = '../temp/memberface/';
            $this->createDir($filePath);

            $result = file_put_contents($filePath.$mediaId[0].".jpg",$a);
            if($result)
            {


                $image=$_SERVER['DOCUMENT_ROOT']."/temp/memberface/".$mediaId[0].".jpg";
                $url ="https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=".$token;
                $file=array(
				    'filename'=>$image,  //相对于网站根目录的路径
				    'content-type'=>'image/png'
				);
		        $data = array('media' =>"@".$image,'form-data'=>$file);
				$ch = curl_init();
				curl_setopt	( $ch, CURLOPT_URL, $url);
				curl_setopt ( $ch, CURLOPT_POST, 1 );
			    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			    curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 5);
			    curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			    curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
			    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
				$info = curl_exec($ch);
				curl_close($ch);

				$info=json_decode($info,true);
                $sql="UPDATE ".tablename('mc_testSeverId')." SET "."openid="."'{$openid}'"." ,wxurl="."'{$info['url']}'"." WHERE serverId="."'{$serverId}'";
                $r=pdo_query($sql);
                if($r){
                	$sql="SELECT code,card_id FROM ".tablename('membership_info')." WHERE openid="."'{$openid}'";

                	$update=pdo_fetch($sql);

                	$face_post='{
					    "code":"'.$update['code'].'",
					    "card_id":"'.$update['card_id'].'",
					    "background_pic_url":"'.$info['url'].'"
					}';
					$face_url="https://api.weixin.qq.com/card/membercard/updateuser?access_token=".$token;
					$s=$this->http_post_attach($face_url,$face_post);
					//var_dump($s);

				}


            }


                // card_C@rD_c0Rd_iVs8ixr

            //$resource = fopen(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR.
            //"temp".DIRECTORY_SEPARATOR."memberface".DIRECTORY_SEPARATOR.date("YmdHis").mt_rand(5).".jpg", 'w+');
            //$ls = fwrite($resource, $a);
            //fclose($resource);
        }

    }
    //微信会员卡  拉起领卡页面
    public function doMobileGetWxcard()
    {
        global $_W;
        $openid = $_W['openid'];
        $result = pdo_fetch("select id from ims_membership_info where openid='{$openid}'");

        //debug_zval_dump($result);

        if (empty($result)) {
            $response = file_get_contents("http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=GetCardSignInfo&m=member");
            $card_id = $this->doMobileGetWxcardid();
            include $this->template('member/getwxcard');

        }else{
            header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=member&m=member");
        }
    }
    //微信会员卡     拉起会员卡card_id
    public function doMobileGetWxcardid(){
        $sql="SELECT card_id FROM ims_mc_create_cards WHERE cur_card_id='1' ";
        $card=pdo_fetch($sql);
        $card_id=$card['card_id'];
        return $card_id;
    }
    //微信会员卡     判断是否拉起
    public  function  doMobileWeakCard(){
        $openid=$_POST['openid'];
        $sql="SELECT create_time,active_time FROM ims_membership_info WHERE openid='{$openid}'";
        $result=pdo_fetch($sql);
        if(empty($result))
        {
            $response=array('status'=>'0','error'=>'NoGetCard');
        }
        elseif (empty($result['active_time']))
        {
            $response=array('status'=>'1','error'=>'NoActive');
        }
        else
        {
            $response=array('status'=>'2','error'=>'Actived');
        }
        $json_response=json_encode($response);
        echo $json_response;
    }


    /**
     * 停车信息
     */
    public function doMobileParkPay()
    {
        global $_W, $_GPC;
        $isUid = false;
        $isPlate = false;
        $isSelect = false;
        $isQuery = false;
        $type = $_GPC['type'];
        $plateNumber = '';
        if($_GPC['plateNumber']){
        	$plateNumber = $_GPC['plateNumber'];
        	$isSelect = true;
        }

        $bannedList = $this->bannedList($plateNumber);

        if(!$bannedList){
            include $this->template('member/end');
            exit();
        }
	    /**
	     * 判断用户是否已经绑定微会员
	     */
	    $uid = mc_openid2uid($_W['openid']);

        // 判断当前会员信息所绑定的车牌是否为查询车牌


	    if (empty($uid)) {
		    $isUid = false;
		    $score = 0;
		    if(empty($plateNumber)){
		    	header('location: '.$this->createMobileUrl('selectPlateNumber',array("floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
		    	exit();
		    }
	    }else{
	    	/**
	    	 * 获取用户积分
	    	 */
	    	$parkScore = pdo_fetchcolumn('select score from ims_park_member where uid = :uid', array('uid' => $uid));               // 停车场专用积分
            //?
            $custId    = pdo_fetch('select custid,cardsn from ims_mc_card_members where uid = :uid', array('uid' => $uid));         // ERP 积分
	    	// ?

            //接口测试4 old
           //      $erpScore  = $this->soapLink()->getCustomerScore($custId['custid']);
            //接口测试4 new
            $scoreParam=array(
                'custId'=>$card['custid'],
            );
            $scoreArr=$this->apiGetCustScore($scoreParam);
            $erpScore=$scoreArr['data']['data']['scoreList'];

	    	// ?
            $score     = $parkScore + $erpScore;                                                                                     // 总积分
	    	$isUid = true;
	    }

	    /**
	     * 判断用户是否绑定车牌号
	     */
	    if(empty($plateNumber)){
        	$pnArr = pdo_fetch('select platenumber from '.tablename('mc_members').' where uid = :uid',array('uid' => $uid));
        	if (empty($pnArr['platenumber']) ) {
		 		$isPlate = false;
		 		 header('location: '.$this->createMobileUrl('selectPlateNumber',array("floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
		 		exit();
	   	 	}else{
	   	 		$plateNumber = $pnArr['platenumber'];
	   	 		$isPlate = true;
	   	 		$isQuery = true;
	   	 	}
	    }else{
	    	$pnArr = pdo_fetch('select platenumber from '.tablename('mc_members').' where uid = :uid',array('uid' => $uid));
	    	if (empty($pnArr['platenumber']) ) {
	    		$isPlate = false;

	    	}else{
	    		$isPlate = true;
	    	}
	    }



	   /* //判断提示语与提示连接
	    if($isQuery == true){
	    	$tipsArr['title'] = "尊敬的会员，您可使用各楼层会员ETC通道快速离场！";
	    	$tipsArr['url'] = "";
	    }elseif ($isUid && $isPlate){
	    	$tipsArr['title'] = "尊敬的会员，您绑定的爱车未入场，您可以在停车中央收费处提前使用积分抵扣停车费快速离场！";
	    	$tipsArr['url'] = "";
	    }elseif ($isUid){
	    	$tipsArr['title'] = "尊敬的会员，您可点击绑定车牌后使用会员ETC通道自动抵扣积分快速离场，并赠送1000停车专用积分！！";
	    	$tipsArr['url'] = $this->createMobileUrl('bindPlaeNumberOnlyOne',array());
	    }else{
	    	$tipsArr['title'] = "尊敬的顾客朋友，您可点击申领会员卡并绑定车牌，成功后可享受会员ETC通道自动抵扣积分快速离场，并赠送1000停车专用积分！";
	    	$tipsArr['url'] = $this->createMobileUrl('userregist',array());
	    }

        $status = 0;*/
	    /**
	     * 获取停车信息
	     */
	    /*
	    $client = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');

        $entryCar = $plateNumber;

        $request = $client->__soapCall('getCarInfo',array(array('carNum'=>$entryCar)));
        $getCardInfo = json_decode($request->getCarInfoResult,true);

        if ($getCardInfo['status'] == 0) {
            header('location: '.$this->createMobileUrl('selectPlateNumber',array('msg'=>'对不起，您输入的车牌已经离场！')));
            exit();
        }
        */


	    $server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
	    $username ="sa";  //用户名
	    $pwd ="Lf0507"; //密码
	    $database ="ACS_Parking20000";  //数据库名称

	    //进行数据库连接]
	    $conn = mssql_connect($server,$username,$pwd) or die ("connect failed");
	    mssql_select_db($database,$conn);
	    //查询入场记录
	    //$sql = "SELECT  *,convert(varchar(19),Crdtm,121) AS inTime  FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' and Crdtm between cast('".date("Y-m-d")." 00:00:00' as datetime) and cast('".date("Y-m-d")." 23:59:59' as datetime) ";
	    $sql = "SELECT TOP 1  *, convert(varchar(19),Crdtm,121) AS inTime FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' ORDER BY Crdtm DESC";

        $sql = iconv('utf-8', 'gb2312', $sql);
	    $rs = mssql_query($sql);
	    $row = mssql_fetch_assoc($rs);




        if(empty($row)){
            $isInMsg = '对不起，您绑定的车辆还未入场，请您输入车牌号查询其他车辆停放情况！';
	    	header('location: '.$this->createMobileUrl('selectPlateNumber',array('msg'=> $isInMsg,"floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
	    	exit();
        }

        $date = strtotime($row['inTime']);
        //$inTime = $row['inTime'];
        $leaveCharge = pdo_fetch("SELECT `platenumber`,`leave_time`,`amount` FROM `ims_park_leave` WHERE `platenumber` ='".$plateNumber."' AND (`create_time` BETWEEN ".$date." AND ".time()." )");



	    //if($row['Recordid']){
        if(empty($leaveCharge)){
	    	$inTime = $row['inTime'];
	    	//计算停车信息
	    	$entryTime =  round((time() - strtotime($row['inTime'])) / 3600,1);
	    	$entryTime = round($entryTime,0);
	    	$getCardInfo = array(
	    			"data" => array(
	    					"amount"  => $entryTime * 3,
	    					"carNum" => iconv('gb2312', 'utf-8',$row['CarCode']),
	    					"carPhoto" => "http://113.140.80.194:8088/img/". iconv('gb2312', 'utf-8', str_replace("\\","/",$row['Carimage'])),
	    					"entryTime" => $row['inTime'],
	    					"leaveTime" => date("Y-m-d H:i:s"),
	    					"time" => date("Y-m-d H:i:s"),
	    					"isLeave" => 0
	    	) );

	    	//查询结算记录
	    	$outTime = $this->getPlateNumberOutTime($plateNumber, $inTime);
	    	if($outTime['OutTime']){
	    		//已离场
	    		if($outTime['OutTime'] == $getCardInfo['data']['entryTime']){
	    			//用户已离场
	    			$isInMsg = "您的爱车已于，".$outTime['OutTime']."驶离停车场，欢迎再次光临！";
	    			header('location: '.$this->createMobileUrl('selectPlateNumber',array('msg'=> $isInMsg,"floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
	    			exit();
	    		}else{
	    			$entryTime =  round((time() - strtotime($outTime['OutTime'])) / 3600,1);
	    			$entryTime = round($entryTime,0);
	    			$getCardInfo['data']['amount'] = $entryTime * 3;
	    			$getCardInfo['data']['entryTime'] = $outTime['OutTime'];
	    			$tipsArr['title'] = $outTime['OutTime']." 已缴纳停车费 ".$outTime['ChargeMoney']."元，感谢您的光临，欢迎下次光临！";
	    			$tipsArr['url'] = "";
	    		}
	    	}
	    }else{
	    	$isInMsg = '对不起，您绑定的车辆已经离场，请您输入车牌号查询其他车辆停放情况！';
	    	if($isSelect){
	    		$isInMsg = '对不起，您输入的车辆已经离场，请您输入车牌号查询其他车辆停放情况！';
	    	}
	    	header('location: '.$this->createMobileUrl('selectPlateNumber',array('msg'=> $isInMsg,"floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
	    	exit();
	    }

        $firstCar = $getCardInfo['data'];

        $firstCar['carNumFormat'] = substr($firstCar['carNum'], 0, 4) . '·' . substr($firstCar['carNum'], 4, 10);

        // 获取用户基本信息
        $userInfo = pdo_fetch('select mobile,realname,idcard from ims_mc_members where uid = :uid',array('uid'=>$uid));
        if($score > $firstCar['amount']*100){
        	$deduct =  $firstCar['amount']*100;
        }elseif( $score < 300 ){
        	$deduct = 0;
        }else{
        	$deduct = intval($score / 300) * 300;
        }
        $firstCar['user'] = array(
            'uid'            => $uid,
            'mobile'         => $userInfo['mobile'],
            'realname'       => $userInfo['realname'],
            'idcard'         => $userInfo['idcard'],
            'custid'         => $custId['custid'],
            'cardsn'         => $custId['cardsn'],
            'carNum'         => $firstCar['carNum'],
            'entryTime'      => $firstCar['entryTime'],
            'leaveTime'      => $firstCar['time'],
            'totalEntryTime' => round((strtotime($firstCar['time']) - strtotime($firstCar['entryTime'])) / 3600, 2),
            'amount'         => $firstCar['amount'],
            'parkScore'      => $parkScore,
            'erpScore'       => $erpScore,
            'score'          => $parkScore + $erpScore,
            'deductScore'    => $firstCar['amount'] * 100
        );

        $jsonStr    = json_encode($firstCar['user']);
        $jsonEncode = authcode($jsonStr, 'ENCODE', 'ParkPay');
        $lastCar = $getCardInfo['data'];

        //停车信息
        $entryInfo = $this->getPlateNumberEntryInfo($plateNumber);
        $entryInfo['bigMap'] = "maparea.png";
		if($entryInfo['ParkingNo']){
			$parkInfo = pdo_fetch('select zone,floor from ims_park_are where parkno = :parkno',array('parkno'=>$entryInfo['ParkingNo']));
			$entryInfo['bigMap'] = $parkInfo['floor']."F".$parkInfo['zone']."Z".".png";
		}elseif($_GPC['floor'] && $_GPC['zone']){
			$entryInfo['bigMap'] = $_GPC['floor']."F".$_GPC['zone']."Z".".png";
		}

        $userinfo = pdo_fetch('select mobile, idcard from'.tablename('mc_members') . 'where uid = :uid', array('uid'=>$uid));

        $memberInfo = mc_fetch($uid,array('mobile','realname','platenumber','platenumber2','platenumber3','isOn','isValued'));


        /**
         *  用户关于赛格会员的信息标识符，
         *
         *  0:  未注册
         *  1： 未绑定车牌
         *  2： 未签约页面
         *  3:  全部状态
         */
        $identifier = 0;

        /**
         * 1. 如果用户没有关注公众号则使用微信支付页面加载
         * 2. 需要用户输入绑定车牌，计算停车时长。
         * 3. 支付成功后，关注赛格国际公众号（支付金额度大于5的金额, ）
         * 4. 如果用户绑定车牌，下次支付直接调起支付页面，支付成功后并提示注册会员可以享用更多的功能
         * 5. 如果用户绑定车牌, 并且已经会员，回调主动支付页面
         *
         */
        $userinfo = pdo_fetch('select mobile, idcard from'.tablename('mc_members') . 'where uid = :uid', array('uid'=>$uid));

        if(!empty($userinfo['mobile']) && !empty($userinfo['idcard'])){
            //
            $userinfo = pdo_fetch('select platenumber, mobile from'.tablename('mc_members') . 'where uid = :uid', array('uid'=>$uid));

            $contractInfo = pdo_fetch('select contract_id from' . tablename('contract_record') . 'where uid = :uid', array('uid' => $uid));

            if (!empty($contractInfo) && empty($userinfo['platenumber'])) {
                $identifier = 10;
                //$this->template('member/contract');
            }
            /**
             * @todo Compose - Wechat Contract Link_Serial~
             */
            $mobile = $userinfo['mobile'];
            $serial = Contract::entrustwebData($mobile);
            $httprequest = ContractContents::SIGN_CONTRACT_HTTP;
            //
            $serial = $httprequest.'?'.$serial;

            // @todo 5: 如果用户绑定车牌号码, (签约查询页面),如果用户未能完成数据签约，则使用签约
            if(!empty($userinfo['platenumber'])){
                // 签约链接的加载数据传递至模板页面
                $contractInfo = pdo_fetch('select contract_id from' . tablename('contract_record') . 'where uid = :uid', array('uid' => $uid));
                if (!empty($contractInfo)) {
                    $identifier = 3;
                    // 全部状态， 尊敬的会员详情页面，(尽情享受会员详情).
                }else{
                    $identifier = 2;
                    // 赛格会员的签约信息页面
                }
            }else {
                $identifier = 1;
                //点此绑定会员的车牌
            }
        }else {
            $identifier = 0;
            // 点击链接可以完成赛格会员的注册;
        }

        $token = md5(uniqid(rand(), TRUE));

        $info = pdo_fetch("SELECT * FROM `ims_park_floor10_test` WHERE `platenumber` = '".$plateNumber."'");

        if(!empty($info['mobile'])){
            $state = 1;
        }else{
            $state = 0;
        }

        include $this->template('member/parkPay');
    }





    /*
     *  检查是否出场
     *  @param plateNumber inTime
     */
    private function getPlateNumberOutTime($plateNumber,$inTime){
    	$server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
    	$username ="sa";  //用户名
    	$pwd ="Lf0507"; //密码
    	$database ="ACS_Parking20000";  //数据库名称
    	$conn = mssql_connect($server,$username,$pwd) or die ("connect failed");
    	mssql_select_db($database,$conn);
    	//查询结算记录
    	$bussql = " select ChargeMoney,convert(varchar(19),OutTm,121) AS OutTime,convert(varchar(19),InTm,121) AS InTime from Tc_Business  where CarCode = '".$plateNumber."'  and    OutTm >= '".$inTime."'   order by OutTm desc";
    	$sql = iconv("UTF-8","gb2312",$bussql);
    	$busRs = mssql_query($sql);
    	$busObj = mssql_fetch_assoc($busRs);
    	if($busObj['OutTime']){
    		return $busObj;
    	}else{
    		return false;
    	}
    }

    /**
     * 生成订单
     */
    public function doMobileSaveOrder()
    {
    	global $_W, $_GPC;

    	if (empty($_GPC['param'])) {
    		header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，您请求的地址不存在！')));
    		exit();
    	}

    	$param  = json_decode(authcode($_GPC['param'], 'DECODE', 'ParkPay'), true);
    	$deduct = intval($_GPC['deduct']);

    	if ($param['amount'] == 0) {
    		header('location: '.$this->createMobileUrl('tips',array('type'=>'v','title'=>'提示信息','msg'=>'您的停车费用为 0 元或已缴费成功，可直接离场')));
    		exit();
    	}

    	if ($deduct < 0) {
    		header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>'您访问的页面不存')));
    		exit();
    	}


    	$order = array(
    			'order_id'    => date('YmdHis') . random(10, true),
    			'openid'      => $_W['openid'],
    			'member_id'   => $param['uid'],
    			'realname'    => $param['realname'],
    			'mobile'      => $param['mobile'],
    			'idcard'      => $param['idcard'],
    			'custid'      => $param['custid'],
    			'cardsn'      => $param['cardsn'],
    			'plateNumber' => $param['carNum'],
    			'entryTime'   => $param['entryTime'],
    			'leaveTime'   => $param['leaveTime'],
    			'duration'    => $param['totalEntryTime'],
    			'money'       => $param['amount'],
    			'status'      => 0,
    			'create_time' => time()
    	);

    	$deductScore = $order['money'] * 100;


    	// 使用积分抵扣
    	if (!empty($deduct)) {
    		// 积分大于停车费时使用停车费完成缴费
            $paymentType = 6;
    		if ($deduct < $deductScore) {
    			$order['leaveTime'] = date('Y-m-d H:i:s',(time() - ($order['duration'] - $deduct/300)*3600));
    			$order['money'] = $deduct/100;
    			$order['duration'] = $deduct/300;

                //$paymentType = 6;
    		}

    			// 停车专用积分
    			$parkScore = $param['parkScore'];
    			// ERP 积分
    			$erpScore = 0;


    			// 如果停车专用积分够支付停车费，
    			if ($parkScore >= $deduct) {
    				$parkScore = $deduct;
    			}
                //

    			if ($parkScore < $deduct) {
    				$erpScore = $deduct - $parkScore;
    			}

    			$order['parkScore'] = $parkScore;
    			$order['erpScore']  = $erpScore;
    			//直接扣除对应积分完成订单
    			try {
    				pdo_begin();
    				$result = pdo_insert('park_order', $order);
    				if ($result == false) {
    					throw new Exception('订单写入失败');
    				}
    				$id = pdo_insertid();

    				//扣除对应积分
    				$tc = "http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl";
    				$client = new SoapClient($tc);
    				$soap = new CardApi();
    				// 抵扣停车专用积分
    				if ($order['parkScore'] > 0) {
    					$parkResult = pdo_query("UPDATE `ims_park_member` SET `score` = `score` - {$order['parkScore']} WHERE `uid` = '{$order['member_id']}' ");
    					if ($parkResult === false) {
    						throw new Exception('停车专用积分扣除失败,请稍后再试');
    					}
    				}

    				// 抵扣ERP积分
    				if ($order['erpScore'] > 0) {
    					$erpResult = $soap->setCustomerScore($order['cardsn'],-$order['erpScore']);
    					if ($erpResult === false) {
    						throw new Exception('ERP积分扣除失败,请稍后再试');
    					}
    				}
    				// 停车场支付接口
    				$payInfo = array(
    						'carNum'    => $order['plateNumber'],
    						'Amount'    => $order['money'],
    						'EntryTime' => $order['entryTime'],
    						'Time'      => $order['leaveTime'],
    						'payment'   => $paymentType
    				);

    				$carResult = $client->__soapCall('payInfo',array($payInfo));
    				$carResultFormat = json_decode($carResult->payInfoResult,true);
    				if ($carResultFormat['status'] == 0) {
    					throw new Exception($carResultFormat['msg']);
    				}

    				// 修改订单状态
    				$park = pdo_query("UPDATE `ims_park_order` SET `status` = 1 WHERE `id` = {$id} ");
    				if ($park === false) {
    					throw new Exception('停车专用积分扣除失败');
    				}

    				// 记录离场信息
    				$park_id = pdo_fetchcolumn('select id from ims_park_member where uid = :uid ',array('uid'=>$order['member_id']));

    				$leaveData = array(
    						'park_id'     => $park_id,
    						'platenumber' => $order['plateNumber'],
    						'leave_time'  => $order['leaveTime'],
    						'score'       => $order['erpScore'] + $order['parkScore'],
    						'amount'      => $order['money'],
    						'duration'    => $order['duration'],
    						'prestore'    => $order['money'],
    						'create_time' => time()
    				);
    				$addEntry = pdo_insert('park_leave',$leaveData);
    				if (!$addEntry) {
    					throw new Exception ('离场信息更新失败！');
    				}

    				// 更新入场记录
    				$changeEntry = pdo_query("UPDATE `ims_park_entry` SET `status` = 1 WHERE `park_id` = {$park_id} order by create_time desc limit 1 ");
    				if ($changeEntry === false) {
    					throw new Exception('入场信息更新失败');
    				}

    				// 记录信息
    				$parkLog = array(
    						'uid'         => $order['member_id'],
    						'openid'      => $order['openid'],
    						'mobile'      => $order['mobile'],
    						'realname'    => $order['realname'],
    						'idcard'      => $order['idcard'],
    						'custid'      => $order['custid'],
    						'cardsn'      => $order['cardsn'],
    						'platenumber' => $order['plateNumber'],
    						'entrytime'   => $order['entryTime'],
    						'leavetime'   => $order['leaveTime'],
    						'duration'    => $order['duration'],
    						'amount'      => $order['money'],
    						'erpscore'    => $order['erpScore'],
    						'parkscore'   => $order['parkScore'],
    						'create_time' => time(),
    						'type'        => 3
    				);
    				$addParkLog = pdo_insert('park_logs',$parkLog);
    				if (!$addParkLog) {
    					throw new Exception('记录信息失败');
    				}

    				// 发送模板消息
    				$acc = WeAccount::create(4);
    				$tempData = array(
    						'first'    => array(
    								'value' => "您好！{$order['realname']}，您的停车费已缴纳：\n",
    								'color' => '#000000'
    										),
    										'keyword1' => array(
    												'value' => '积分抵扣',
    												'color' => '#69008C'
    										),
    										'keyword2' => array(
    												'value' => $order['money'].' 元',
    												'color' => '#69008C'
    										),
    										'remark'   => array(
    												'value' => '您停车时长为 '.$order['duration'].' 小时，共'.$order['money'].'元，已使用'.($order['parkScore'] + $order['erpScore']).'积分抵扣'.$order['money'].'元停车费。如有问题，详情请致电029-86300000',
    												'color' => '#000000'
    										),
    				);
    				$rss = $acc->sendTplNotice($order['openid'], 'BB75yZ4znD8nuoyYaAMlwXDdzRPZkcURIMo61kcdzWY', $tempData, '', '#69008C');
    				pdo_commit();
    			} catch (Exception $e) {
    				pdo_rollback();
    				logs($e->getMessage());
    				header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>$e->getMessage())));
    				exit();
    			}
    			if($deduct >= $deductScore){
    				header('location: http://wx.cnsaga.com/app/index.php?i=4&c=entry&type=v&title=提示信息&msg=您已缴费成功，可直接离场，请勿点击返回导致重复缴费！&do=tips&m=member');
    				exit();
    			}else{
    				$order['entryTime'] = $order['leaveTime'];
    				$order['leaveTime'] = date('Y-m-d H:i:s');
    				$order['duration'] =  round((strtotime($order['leaveTime']) - strtotime($order['entryTime'])) / 3600, 2);
    				$duration = round($order['duration'],0);
    				$order['money'] = $duration * 3;
    				$order['order_id'] = date('YmdHis') . random(10, true);
    				$order['deduct'] = 0;
    				$order['erpScore'] = 0;
    				$order['parkScore'] = 0;
    				$result = pdo_insert('park_order', $order);
    				if ($result == false) {
    					throw new Exception('订单写入失败2');
    					logs("创建支付订单失败");
    				}
    				$id = pdo_insertid();
    				header("location: ../payment/wechat/wechatPark.php?i={$_W['uniacid']}&ps={$id}&order={$order['order_id']}");
    				exit();
    			}
    	}else{
    		//不使用积分 直接生产订单支付
    		try {
    			pdo_begin();
    			$result = pdo_insert('park_order', $order);
    			if ($result == false) {
    				throw new Exception('订单写入失败');
    			}
    			$id = pdo_insertid();
    			pdo_commit();
    			header("location: ../payment/wechat/wechatPark.php?i={$_W['uniacid']}&ps={$id}&order={$order['order_id']}");
    			exit();
    		} catch (Exception $e) {
    			pdo_rollback();
    			header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>$e->getMessage())));
    			exit();
    		}
    	}
    }

    /**
     * 生成订单
     */
    public function doMobileBuyScore()
    {
        global $_W, $_GPC;

        if (empty($_GPC['param'])) {
            header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'信息有误','msg'=>'对不起，您请求的地址不存在！')));
            exit();
        }

        $param  = json_decode(authcode($_GPC['param'], 'DECODE', 'ParkPay'), true);
        $deduct = intval($_GPC['deduct']);

        if ($param['amount'] == 0) {
            header('location: '.$this->createMobileUrl('tips',array('type'=>'v','title'=>'提示信息','msg'=>'您的停车费用为 0 元或已缴费成功，可直接离场')));
            exit();
        }

        if ($deduct < 0) {
            header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>'您访问的页面不存')));
            exit();
        }


        $order = array(
            'order_id'    => date('YmdHis') . random(10, true),
            'openid'      => $_W['openid'],
            'member_id'   => $param['uid'],
            'realname'    => $param['realname'],
            'mobile'      => $param['mobile'],
            'idcard'      => $param['idcard'],
            'custid'      => $param['custid'],
            'cardsn'      => $param['cardsn'],
            'plateNumber' => $param['carNum'],
            'entryTime'   => $param['entryTime'],
            'leaveTime'   => $param['leaveTime'],
            'duration'    => $param['totalEntryTime'],
            'money'       => $param['amount'],
            'status'      => 0,
            'create_time' => time()
        );

        // 使用积分抵扣
        if (!empty($deduct)) {

            // 因其它问题导致顾客输入的积分大于应付积分（全款金额*100）时。直接取应付积分
            if ($deduct > $param['deductScore']) {
                $deduct = $param['deductScore'];
            }

            // 停车专用积分
            $parkScore = $param['parkScore'];
            // ERP 积分
            $erpScore = 0;

            // 如果停车专用积分够支付停车费，
            if ($parkScore >= $deduct) {
                $parkScore = $deduct;
            }

            if ($parkScore < $deduct) {
                $deductErp = $deduct - $parkScore;

                // ERP中顾客总积分够支付剩余抵扣积分时直接抵扣，如果不够则为0（将这部分积分换算为金额通过微信支付收取）
                if ($param['erpScore'] >= $deductErp) {
                    $erpScore = $deductErp;
                }
            }

            $order['deduct']    = 1;
            $order['parkScore'] = $parkScore;
            $order['erpScore']  = $erpScore;
        }


        try {
            pdo_begin();

            $result = pdo_insert('park_order', $order);
            if ($result == false) {
                throw new Exception('订单写入失败');
            }
            $id = pdo_insertid();
            pdo_commit();
            header("location: ../payment/wechat/wechatPark.php?i={$_W['uniacid']}&ps={$id}&order={$order['order_id']}");
            exit();
        } catch (Exception $e) {
            pdo_rollback();
            header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'提示信息','msg'=>$e->getMessage())));
            exit();
        }
    }

    /**
     * 通用提示
     */
    public function doMobileTips(){
        global $_W,$_GPC;
        include $this->template('member/tips');
    }


    /**
     * 扫码支付加载信息提示
     */
    public function doMobileQrpay(){
        global $_W, $_GPC;

        $uid = mc_openid2uid($_W['openid']);

        /**
         *  用户关于赛格会员的信息标识符，
         *
         *  0:  未注册
         *  1： 未绑定车牌
         *  2： 未签约页面
         *  3:  全部状态
         */
        $identifier = 0;

        /**
         * 1. 如果用户没有关注公众号则使用微信支付页面加载
         * 2. 需要用户输入绑定车牌，计算停车时长。
         * 3. 支付成功后，关注赛格国际公众号（支付金额度大于5的金额, ）
         * 4. 如果用户绑定车牌，下次支付直接调起支付页面，支付成功后并提示注册会员可以享用更多的功能
         * 5. 如果用户绑定车牌, 并且已经会员，回调主动支付页面
         *
         */
        $userinfo = pdo_fetch('select mobile, idcard from'.tablename('mc_members') . 'where uid = :uid', array('uid'=>$uid));

        if(!empty($userinfo['mobile']) && !empty($userinfo['idcard'])){
            //
            $userinfo = pdo_fetch('select platenumber, mobile from'.tablename('mc_members') . 'where uid = :uid', array('uid'=>$uid));

            $contractInfo = pdo_fetch('select contract_id from' . tablename('contract_record') . 'where uid = :uid', array('uid' => $uid));

            if (!empty($contractInfo) && empty($userinfo['platenumber'])) {
                $identifier = 10;
                //$this->template('member/contract');
            }
            /**
             * @todo Compose - Wechat Contract Link_Serial~
             */
            $mobile = $userinfo['mobile'];
            $serial = Contract::entrustwebData($mobile);
            $httprequest = ContractContents::SIGN_CONTRACT_HTTP;
            //
            $linkSerial = $httprequest.'?'.$serial;

            // @todo 5: 如果用户绑定车牌号码, (签约查询页面),如果用户未能完成数据签约，则使用签约
            if(!empty($userinfo['platenumber'])){
                // 签约链接的加载数据传递至模板页面
                $contractInfo = pdo_fetch('select contract_id from' . tablename('contract_record') . 'where uid = :uid', array('uid' => $uid));
                if (!empty($contractInfo)) {
                    $identifier = 3;
                    // 全部状态， 尊敬的会员详情页面，(尽情享受会员详情).
                }else{
                    $identifier = 2;
                    // 赛格会员的签约信息页面
                }
            }else {
                $identifier = 1;
                //点此绑定会员的车牌
            }
        }else {
            $identifier = 0;
            // 点击链接可以完成赛格会员的注册;
        }
        include $this->template('member/guide');
    }

    /**
     * 绑定车牌号
     */
    public function doMobilePlateNumber()
    {
        global $_W, $_GPC;

        $uid = mc_openid2uid($_W['openid']);

        if (!$uid) {
            header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1');
            exit();
        }

        $userinfo                 = pdo_fetch('select platenumber,platenumber2,platenumber3,isOn from ' . tablename('mc_members') . ' where uid = :uid', array('uid' => $uid));
        $userinfo['platenumber']  = empty($userinfo['platenumber']) ? '' : substr($userinfo['platenumber'], 0, 4) . '·' . substr($userinfo['platenumber'], 4, 10);
        $userinfo['platenumber2'] = empty($userinfo['platenumber2']) ? '' : substr($userinfo['platenumber2'], 0, 4) . '·' . substr($userinfo['platenumber2'], 4, 10);
        $userinfo['platenumber3'] = empty($userinfo['platenumber3']) ? '' : substr($userinfo['platenumber3'], 0, 4) . '·' . substr($userinfo['platenumber3'], 4, 10);

        if ($_W['ispost'] && $_W['isajax']) {
            $client = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');

            $field       = ($_GPC['num'] == 1) ? 'platenumber' : 'platenumber' . $_GPC['num'];
            $platenumber = str_replace('·', '', $_GPC['platenumber']);
            $isOn        = $_GPC['isOn'];

            $platenumbers = pdo_fetch("select `platenumber`,`platenumber2`,`platenumber3` from `ims_mc_members` where `platenumber` = '{$platenumber}' or `platenumber2` = '{$platenumber}' or `platenumber3` = '{$platenumber}'");
            if (!empty($platenumbers['platenumber']) || !empty($platenumbers['platenumber2']) || !empty($platenumbers['platenumber3'])) {
                echo json_encode(array('status' => 0, 'msg' => '车牌号 ' . $platenumber . ' 已经绑定过，不能重复绑定'));
                exit();
            }

            /**
             * 首次绑定送1000积分
             */
            $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid", array('uid' => $uid));
            if ($count == 0) {
                $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
                $id     = pdo_insertid();
            }

            if (!empty($id)) {
                $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid", array('uid' => $uid));
                if ($parkCount == 0) {
                    $memberInfo = mc_fetch($uid, array('mobile', 'realname'));
                    pdo_insert('park_member', array('openid'   => $_W['openid'],
                                                    'uid'      => $uid,
                                                    'score'    => 1000,
                                                    'mobile'   => $memberInfo['mobile'],
                                                    'realname' => $memberInfo['realname'],
                                                    'create_time' => TIMESTAMP
                    ));
                } else {
                    pdo_query("UPDATE `ims_park_member` SET `score` = score + 1000 WHERE `uid` = '{$uid}'");
                }
            }

            $getRequest = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $platenumber)));

            if (empty($getRequest->GetMemberPlateNumberInfoResult)) {
                $addRequest = $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $platenumber)));
                if ($addRequest->AddMemberPlateNumberResult) {
                    $upPlate = pdo_update('mc_members', array('isOn' => $isOn, $field => $platenumber), array('uid' => $uid));
                    if ($upPlate !== false) {
                        echo json_encode(array('status' => 1, 'msg' => '绑定车牌号成功', 'platenumber' => $platenumber));
                        exit();
                    } else {
                        echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                        exit();
                    }
                } else {
                    echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                    exit();
                }
            } else {
                $upPlate = pdo_update('mc_members', array('isOn' => $isOn, $field => $platenumber), array('uid' => $uid));
                if ($upPlate !== false) {
                    echo json_encode(array('status' => 1, 'msg' => '绑定车牌号成功', 'platenumber' => $platenumber));
                    exit();
                } else {
                    echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                    exit();
                }
            }

        } else {
            if (empty($userinfo['platenumber']) && empty($userinfo['platenumber2']) && empty($userinfo['platenumber3']) ){
                $isOnTag = 1;
            }
            if(date('Ymd') < '20160301'){
                include $this->template('member/bindCardNumOnlyOne');
            }else{
                header('location: '.$this->createMobileUrl('bindPlateNumberOnlyOne'));
                exit();
           	}
        }

    }

    /**
     * 绑定车牌号(仅一个)
     */
    public function doMobileBindPlateNumberOnlyOne()
    {
        global $_W, $_GPC;

        $uid = mc_openid2uid($_W['openid']);

        if (!$uid) {
            header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&eid=1');
            exit();
        }

        $userinfo                 = pdo_fetch('select platenumber,isOn,bind_time from ' . tablename('mc_members') . ' where uid = :uid', array('uid' => $uid));
        $userinfo['platenumber']  = empty($userinfo['platenumber']) ? '' : substr($userinfo['platenumber'], 0, 4) . '·' . substr($userinfo['platenumber'], 4, 10);

        if ($_W['ispost'] && $_W['isajax']) {
            $client = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');

            $platenumber = str_replace('·', '', $_GPC['platenumber']);
            $isOn        = $_GPC['isOn'];

            $platenumbers = pdo_fetch("select `platenumber`,`platenumber2`,`platenumber3` from `ims_mc_members` where `platenumber` = '{$platenumber}' or `platenumber2` = '{$platenumber}' or `platenumber3` = '{$platenumber}'");
            if (!empty($platenumbers['platenumber']) || !empty($platenumbers['platenumber2']) || !empty($platenumbers['platenumber3'])) {
                echo json_encode(array('status' => 0, 'msg' => '车牌号 ' . $platenumber . ' 已经绑定过，不能重复绑定'));
                exit();
            }

            /**
             * 首次绑定送1000积分
             */
            $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid", array('uid' => $uid));
            if ($count == 0) {
                $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
                $id     = pdo_insertid();
            }

            if (!empty($id)) {
                $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid", array('uid' => $uid));
                if ($parkCount == 0) {
                    $memberInfo = mc_fetch($uid, array('mobile', 'realname'));
                    pdo_insert('park_member', array('openid'   => $_W['openid'],
                                                    'uid'      => $uid,
                                                    'score'    => 1000,
                                                    'mobile'   => $memberInfo['mobile'],
                                                    'realname' => $memberInfo['realname'],
                                                    'create_time' => TIMESTAMP
                    ));
                } else {
                    pdo_query("UPDATE `ims_park_member` SET `score` = score + 1000 WHERE `uid` = '{$uid}'");
                }
            }

            $getRequest = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $platenumber)));

            $bindInfo = pdo_fetch('select bind_time from ' . tablename('mc_platenumber_bind_log') . ' where  uid = :uid', array('uid'=>$uid));
            $bindStatus = 1;
            if (!empty($bindInfo['bind_time'])){
                $time = date('Ymd');
                $bindTime = date('Ymd',strtotime('+1 year',$userinfo['bind_time']));
                if ($time < $bindTime) {
                    $bindStatus = 0;
                }
            }

            if (empty($getRequest->GetMemberPlateNumberInfoResult)) {
                $addRequest = $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $platenumber)));
                if ($addRequest->AddMemberPlateNumberResult) {
                    $upPlate = pdo_update('mc_members', array('isOn' => $isOn, 'platenumber' => $platenumber,'bind_time'=>time()), array('uid' => $uid));
                    if ($upPlate !== false) {
                        echo json_encode(array('status' => 1, 'msg' => '绑定车牌号成功', 'platenumber' => $platenumber,'bindStatus'=>$bindStatus));
                        exit();
                    } else {
                        echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                        exit();
                    }
                } else {
                    echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                    exit();
                }
            } else {
                $upPlate = pdo_update('mc_members', array('isOn' => $isOn, 'platenumber' => $platenumber,'bind_time'=>time()), array('uid' => $uid));
                if ($upPlate !== false) {

                    echo json_encode(array('status' => 1, 'msg' => '绑定车牌号成功', 'platenumber' => $platenumber,'bindStatus'=>$bindStatus));
                    exit();
                } else {
                    echo json_encode(array('status' => 0, 'msg' => '绑定车牌号失败', 'platenumber' => $platenumber));
                    exit();
                }
            }

        } else {
            if (empty($userinfo['platenumber'])) {
                $platenumber = '';
                $status = 0;
            }
            if (!empty($userinfo['platenumber']) && $userinfo['isOn'] == 1) {
                $platenumber = $userinfo['platenumber'];
                $status = 1;
            }

            if (!empty($userinfo['platenumber']) && $userinfo['isOn'] == 0) {
                $platenumber = $userinfo['platenumber'];
                $status = 2;
            }


            $bindInfo = pdo_fetch('select bind_time from ' . tablename('mc_platenumber_bind_log') . ' where  uid = :uid', array('uid'=>$uid));
            $bindStatus = 1;
            /*if (!empty($bindInfo['bind_time'])){
                $time = date('Ymd');
                $bindTime = date('Ymd',strtotime('+1 year',$userinfo['bind_time']));
                if ($time < $bindTime) {
                    $bindStatus = 0;
                }
            }*/

            include $this->template('park/BindCardNum');
        }

    }

    /**
     * 删除车牌号（仅一个）
     */
    public function doMobileDelPlateNumberOnlyOne()
    {
        global $_W,$_GPC;

        if ($_W['ispost'] && $_W['isajax']) {

            $client = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');

            $platenumber = str_replace('·','',$_GPC['platenumber']);

            $getRequest = $client->__soapCall('GetMemberPlateNumberInfo',array(array('plateNumber'=>$platenumber)));
            if (!empty($getRequest->GetMemberPlateNumberInfoResult)) {
                $client->__soapCall('DeleteMemberPlateNumber',array(array('plateNumber'=>$platenumber)));
            }

            $bindInfo = pdo_fetch('select bind_time from ' . tablename('mc_platenumber_bind_log') . ' where  uid = :uid', array('uid'=>$_GPC['uid']));

            $bindStatus = 0;
            if (empty($bindInfo)) {
                pdo_insert('mc_platenumber_bind_log',array('uid'=>$_GPC['uid'],'bind_time'=>time()));
            }

            if (!empty($bindInfo['bind_time'])){
                $time = date('Ymd');
                $bindTime = date('Ymd',strtotime('+1 year',$bindInfo['bind_time']));
                if ($time > $bindTime) {
                    $bindStatus = 1;
                    pdo_update('mc_platenumber_bind_log',array('bind_time'=>time()),array('uid'=>$_GPC['uid']));
                }
            }

            $result = pdo_update('mc_members', array('platenumber' => '','isOn'=>0), array('platenumber' => $platenumber));


            if($result !== false){
                echo json_encode(array('status'=>1,'msg'=>'解除绑定成功','bindStatus'=>$bindStatus));
            } else {
                echo json_encode(array('status'=>0,'msg'=>'解除绑定失败'));
            }
        } else {
            exit('error');
        }
    }

    /*
     * 高级会员特权页
     */
    public function doMobileRights()
    {
    	global $_W,$_GPC;
    	include $this->template('member/Rights');
    }
    /*
     *  显示活动H5页面
     */
    public function doMobileActivityH5()
    {
    	global $_W,$_GPC;
    	$act_id = $_GPC['act_id'];
    	switch($act_id){
    		case '20160618':
    			$shareData = array(
    				'title' => '父亲节  赛格国际捡钱 抽奔驰攻略',
    				'desc' => '赛格携手飞凡撒钱了，奔驰、iPhone、代金券都白送！思聪老公你怎么看？'
    			);
    			break;
    	}
    	include $this->template('activity/'.$act_id.'/index');
    }

    public function doMobileshowRights()
    {
        global $_W,$_GPC;


        include $this->template('member/showRights');
    }

    /**
     * 删除车牌号
     */
    public function doMobileDelPlateNumber()
    {
        global $_W,$_GPC;

        if ($_W['ispost'] && $_W['isajax']) {

            $client = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');

            $field = ($_GPC['num'] == 1) ? 'platenumber':'platenumber'.$_GPC['num'];

            $platenumber = str_replace('·','',$_GPC['platenumber']);

            $getRequest = $client->__soapCall('GetMemberPlateNumberInfo',array(array('plateNumber'=>$platenumber)));
            if (!empty($getRequest->GetMemberPlateNumberInfoResult)) {
                $client->__soapCall('DeleteMemberPlateNumber',array(array('plateNumber'=>$platenumber)));
            }

            switch ($_GPC['num']) {
            	case 1:
            		$sql = " update ims_mc_members set platenumber = platenumber2,platenumber2 = platenumber3, platenumber3 = '' where platenumber = '".$platenumber."' ";
            		break;
            	case 2:
            		$sql = " update ims_mc_members set platenumber2 = platenumber3 , platenumber3 = '' where platenumber2 = '".$platenumber."' ";
            		break;
            	case 3:
            		$sql = " update ims_mc_members set platenumber3 = '' where platenumber3 = '".$platenumber."' ";
            		break;
            }

            if(date('Ymd') >= '20160128'){
            	$result = pdo_query($sql);
            }else{
            	$result = pdo_update('mc_members', array($field => ''), array($field => $platenumber));
        	}

            if($result !== false){
                echo json_encode(array('status'=>1,'msg'=>'解除绑定成功'));
            } else {
                echo json_encode(array('status'=>0,'msg'=>'解除绑定失败'));
            }
        } else {
            exit('error');
        }
    }

    /**
     * 是否使用积分抵扣停车费ICON
     */
    public function doMobilesetIson()
    {
        global $_W,$_GPC;

        if ($_W['ispost'] && $_W['isajax']) {

            $client   = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');
            $uid      = mc_openid2uid($_W['openid']);
            $userinfo = pdo_fetch('select platenumber,platenumber2,platenumber3,isOn from ' . tablename('mc_members') . ' where uid = :uid', array('uid' => $uid));

            if ($_GPC['isOn'] == 1) {

                if (!empty($userinfo['platenumber'])) {
                    $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber'])));
                    if (empty($getRequest1->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber'])));
                    }
                }

                if (!empty($userinfo['platenumber2'])) {
                    $getRequest2 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber2'])));
                    if (empty($getRequest2->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber2'])));
                    }
                }

                if (!empty($userinfo['platenumber3'])) {
                    $getRequest3 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber3'])));
                    if (empty($getRequest3->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber3'])));
                    }
                }


            } else {

                if (!empty($userinfo['platenumber'])) {
                    $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber'])));
                    if (!empty($getRequest1->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('DeleteMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber'])));
                    }
                }

                if (!empty($userinfo['platenumber2'])) {
                    $getRequest2 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber2'])));
                    if (!empty($getRequest2->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('DeleteMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber2'])));
                    }
                }

                if (!empty($userinfo['platenumber3'])) {
                    $getRequest3 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $userinfo['platenumber3'])));
                    if (!empty($getRequest3->GetMemberPlateNumberInfoResult)) {
                        $client->__soapCall('DeleteMemberPlateNumber', array(array('plateNumber' => $userinfo['platenumber3'])));
                    }
                }
            }

            $result = pdo_update('mc_members', array('isOn' => $_GPC['isOn']), array('uid' => $uid));
            if($result !== false){
                echo json_encode(array('status'=>1,'msg'=>'设置积分抵扣停车费成功'));
            } else {
                echo json_encode(array('status'=>0,'msg'=>'设置积分抵扣停车费成功'));
            }
        } else {
            exit('error');
        }
    }

    /**
     * 发送短信
     */
    public  function doMobileSend(){
		global $_GPC;
		load()->func('cache.mysql');
		$tel  = $_GPC['phone'];
		$code = random(6,true);
		$msg  = "【西安赛格】短信验证码为：" . $code . ",请您在30分钟内完成,如非本人操作,请忽略。退订回TD";
		$preg = preg_match('/^\d{11}$/',$tel);
		if(!$preg  || empty($tel)){
			echo json_encode(array('status'=>0,'text'=>'手机号必填或格式不正确'));
			exit();
		}
		$cache = cache_load($_GPC['phone']);
		if(empty($cache)){
			cache_write($_GPC['phone'],array('code'=>$code,'create_time'=>(TIMESTAMP+1800)));
			$this->sendmsg($tel,$msg);
			echo json_encode(array('status'=>1,'text'=>'短信已发送，请注意查收'));
			exit();
		}else{
			//已过期
			if($cache['create_time'] < TIMESTAMP){
				cache_write($_GPC['phone'],array('code'=>$code,'create_time'=>(TIMESTAMP+1800)));
				$this->sendmsg($tel,$msg);
				echo json_encode(array('status'=>1,'text'=>'短信已发送，请注意查收'));exit();
			}else{
			//未过期
				echo json_encode(array('status'=>1,'text'=>'您好，验证码时效为30分钟，请直接填写现有验证码。'));exit();
			}
		}
	}
	//短信接口
    public function sendmsg($tel, $msg)
    {
        load()->func('communication');
        $msg    = iconv("UTF-8", "gb2312//IGNORE", $msg);
        $status = ihttp_post('http://58.83.147.92:8080/qxt/smssenderv2', array('user'     => 'zs_saige',
                                                                               'password' => strtolower(md5('521748')),
                                                                               'tele'     => $tel,
                                                                               'msg'      => $msg
        ));
        if ($status['code'] == "200" && $status['status'] == 'ok') {
            return true;
        } else {
            return false;
        }
    }

    public function doMobileGetScore(){
        global $_GPC;

        //接口测试5 old
    //       $score = $this->soapLink()->getCustomerScore($_GPC['custid']);
        //接口测试5 new
        $scoreParam=array(
            'custId'=>$_GPC['custid'],
        );
        $scoreArr=$this->apiGetCustScore($scoreParam);

        $score=$scoreArr['data']['data']['scoreList'];
        $status = empty($score) ? 0 :1;
        echo json_encode(array('score'=>$score,'status'=>$status));
    }

    public function doMobileSetScore(){
        global $_GPC;
        //接口测试6 old
            //  $score = $this->soapLink()->setCustomerScore($_GPC['cardId'],-$_GPC['score']);
        //接口测试6 new
        $scoreParam=array(
            'cardId'=>$_GPC['cardId'],
             'scoreNum'=>-$_GPC['score'],
        );
        $scoreArr=$this->apiSetCustScore($scoreParam);

        $score=$scoreArr['data']['data']['scoreList'];

        $status = empty($score) ? 0 :1;
        echo json_encode(array('score'=>$_GPC['score'],'status'=>$status));
    }

    /*
     * 反向寻车大地图显示
     * edit by dv
     * doMobileFindCar()
     */
    public function doMobileFindCar(){
    	global $_W,$_GPC;
    	if($_GPC['ParkingNo']){
    		//获取停车位坐标
    		$posArr = $this->GetParkNoInfo($_GPC['ParkingNo']);


            //@todo 查询停车区域
            $zoneName = pdo_fetch("SELECT `zone_name` FROM `ims_park_are` WHERE `parkno`= '".$_GPC['ParkingNo']."'");


            if(!empty($zoneName)){
                $zoneName = $zoneName['zone_name'];
            }else{
                $zoneName = '';
            }


            //$zoneName = '';


            //@todo 查询数据库车位编号, (X, Y) 车位编号坐标, 加载查询信息
    	}
    	if($_GPC['floor'] == 8 ){
    		include $this->template('member/FindCar8f');
    	}else if($_GPC['floor'] == 9){
            include $this->template('member/FindCar9f');
        }else{
            include $this->template('member/FindCar10f');
        }
    }
    /*
     * 根据停车位编号返回地图对应坐标
     * edit by dv
     * GetParkNoInfo()
     */
    public function GetParkNoInfo($parkingNo){

        /**
         * 根据本地数据停车位置信息加载
         */
    	if($parkingNo){
    		$pArr = explode('F',$parkingNo);
    		if($pArr[0] == 10){

	    		/*$server ="dbpark";
	    		//服务器IP地址,如果是本地，可以写成localhost
	    		$uid ="sa";  //用户名
	    		$pwd ="lf0507"; //密码
	    		$database ="parkinginner";  //数据库名称
	    		//进行数据库连接
	    		$conn =mssql_connect($server,$uid,$pwd) or die ("connect failed".mssql_get_last_message());
	    		mssql_select_db($database,$conn);
	    		$query = "  select ArrPositions from gd_Parking where ParkingNo = '".$parkingNo."' ";
	    		$rs  = mssql_query($query);
	    		$row = mssql_fetch_assoc($rs);
	    		if($row['ArrPositions']){
	    			$tmpArr = explode("#", $row['ArrPositions']);
	    			$rsArr = explode(",", $tmpArr[0]);
	    			return $rsArr;
	    		}*/

                $rs = pdo_fetch("SELECT * FROM ims_park_are WHERE parkno = '".$parkingNo."' ");
                $PosArr = array($rs['x'], $rs['y']);
                return $PosArr;
    		}else if($pArr[0] == 8){
    		    //@todo 数据坐标信息
                $rs = pdo_fetch(" select * from  ims_park_are where parkno = '".$parkingNo."' ");
                $PosArr = array($rs['x'], $rs['y']);
                return $PosArr;
    		}else if($pArr[0] == 9){
                $rs = pdo_fetch("SELECT * FROM ims_park_are WHERE `parkno`='".$parkingNo."' ");
                $PosArr = array($rs['x'], $rs['y']);
                return $PosArr;
            }
    	}
    	return false;
    }
    public function doMobileSetParkNoPosition(){}

    /*
     * 通过$plateNumber返回用户停车位置
     * getPlateNumberEntryInfo($plateNumber)
     * @parm $plateNumber 车牌号
     */
     private  function getPlateNumberEntryInfo($plateNumber){

             // 非微信会员数据存储
            $currentTime = time();

             //当天的起止时间
            $y = date("Y");
            $m = date("m");
            $d = date("d");
             /**
              * 当天的起止时间
              */
            $day_start = mktime(0,0,0,$m,$d,$y);
            $day_end   = mktime(23,59,59,$m,$d,$y);

            $tmp = pdo_fetch("SELECT * FROM `ims_parkinfo_status` WHERE `platenumber`= '".$plateNumber."'AND ( `create_time` BETWEEN ".$day_start." AND ".$day_end." )  ORDER BY `create_time` DESC LIMIT 1");


            $tmp['plateNumber'] = $tmp['platenumber'];
            $tmp['img'] = "http://113.140.80.197/img/".str_replace("\\","/",$tmp['park_img']);
            $tmp['ParkingNo'] = $tmp['parkno'];



            /*$server ="dbpark";  //服务器IP地址,如果是本地，可以写成localhost
            $uid ="sa";  //用户名
            $pwd ="lf0507"; //密码
            $database ="parkinginner";  //数据库名称
            //$plateNumber = "陕A%";
            //进行数据库连接
            $conn =mssql_connect($server,$uid,$pwd) or die ("connect failed".mssql_get_last_message());
            mssql_select_db($database,$conn);

            //执行查询语句
            $query ="SELECT a.ImgName,b.ParkingNo,a.LicensePlateNumber  FROM gd_LicenseRecognize as a left join gd_Parking as b on a.RealParkingId = b.RecordId where a.LicensePlateNumber like '". $plateNumber ."'";
            $query = iconv('utf-8', 'gb2312', $query);
            $row =mssql_query($query);
            while($list=mssql_fetch_assoc($row))
            {
                $img = iconv("gb2312","utf-8",$list['ImgName']);
                $tmp['plateNumber'] = iconv("gb2312","utf-8",$list['LicensePlateNumber']);
                $tmp['img'] = "http://113.140.80.197/img/".str_replace("\\","/",$img);
                $tmp['ParkingNo'] = $list['ParkingNo'];
            }*/


            $rs = pdo_fetch(" select * from ims_park_are where parkno = '".$tmp['parkno']."' ");
            $tmp['parkName'] = $rs['floor']."楼".$rs['zone_name'];
            return $tmp;
     }

     /*
      * 通过$plateNumber返回入场信息
      * edit by 李欣
      * getParkingInfo($plateNumber)
      *
      */
     private  function getParkingInfo($plateNumber){
    	$startTime = time()-86400*3;
    	//查询入场停车的信息
    	$plateInfo = pdo_fetch('select entry_time,status from '.tablename('park_entry').' where platenumber = :platenumber and status=0 and create_time>=starttime',array('platenumber' => $plateNumber,'startTime'=>$starTime));
    	 if (empty($plateInfo))  {
    	 	return false;
    	 }else {
    		return $plateInfo;
         }
    }


    /*
     * 通过uid返回用户车牌号
     * edit by 李欣
     * getMemberPlateNumber($uid)
     * @param $uid 会员编号
     */
	private function getMemberPlateNumber($uid){

		//查询出会员的车牌号
		$plateNumber = pdo_fetch('select platenumber,platenumber2,platenumber3 from '.tablename('mc_members').' where uid = :uid',array('uid' => $uid));

		if(empty($plateNumber['platenumber'])){   //如果变量是非空或非零的值，则 empty() 返回 FALSE。
			return false;
		}else{
			return $plateNumber['platenumber'];
		}

	}

    public function soapLink()
    {
        $soap = new CardApi();
        return $soap;
    }

    public function doMobileGetToken()
    {
    	$token = pdo_fetch("SELECT `access_token` FROM `ims_account_wechats` WHERE acid = 4");

        $token = unserialize($token['access_token']);

        if($token['expire'] > time()){
            return $token['token'];
        }else{
            $re = file_get_contents("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wxc5c898f251c5fa05&secret=a0a42f79247cd243d25b32fc8e979d19");

            $storage = json_decode($re, true);

            $info_array = array();
            $info_array['expire'] = $storage['expire_in'] + time();
            $info_array['token']  = $storage['access_token'];

            pdo_update('ims_account_wechats', array('access_token' => serialize($info_array)),  array('acid' =>4 ));

            return $info_array['token'];
        }

        //$weiObj = WeAccount::create(4);
        //echo $weiObj->fetch_token();
    }

    /**
     *
     */
//  public function doMobileReToken()
//  {
//      $token = pdo_fetch("SELECT `access_token` FROM `ims_account_wechats` WHERE acid = 4");
//
//      $token = unserialize($token['access_token']);
//
//      if($token['expire'] > time()){
//          return $token['token'];
//      }else{
//          $re = file_get_contents("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=wxc5c898f251c5fa05&secret=a0a42f79247cd243d25b32fc8e979d19");
//
//          $storage = json_decode($re, true);
//
//          $info_array = array();
//          $info_array['expire'] = $storage['expire_in'] + time();
//          $info_array['token']  = $storage['access_token'];
//
//          pdo_update('ims_account_wechats', array('access_token' => serialize($info_array)),  array('acid' =>4 ));
//
//          return $info_array['token'];
//      }
//  }
    //照片活动开始
    public function doMobileActivityIndex(){
    	global $_W,$_GPC;
    	$act_id = $_GPC['act_id'];
    	switch( $act_id ){
    			case '20160624':
    				$shareData = array(
    				'title' => '比基尼宝贝秀 晒照赢500元现金大奖',
    				'desc' => '助阵欧洲杯，谁是最具魅力比基尼宝贝？马上参与'
    						);
    				$template = "activity/20160624/index";
    				$tableName = "0624_day";
    			break;
    			case '20160714':
    				$shareData = array(
    				'title' => '“土妞”变成“洋妹子”姑凉们的成长',
    				'desc' => '所谓女大十八变，越变越漂亮，看看你的土妞变形记'
    						);
    				$template = "activity/20160714/index";
    				$tableName = "20160714_day";
    				break;
    			case '20160715':
    					$shareData = array(
    					'title' => '学霸，你录取通知书到了',
    					'desc' => '制作录取通知书，领红包助学金。带你装X带你飞。'
    							);
    							$template = "activity/20160715/index";
    							$tableName = "20160715_day";
    							$vtableName = "20160715_day_vote";
                    break;
                    case '20160901':
                    	$shareData = array(
                    	'title' => '谁是演唱会“潮装达人”？  摇滚的夜晚  够潮才对',
    					'desc' => '上传照片，用潮范儿展现你青春的一面，重获力量，让青春的血液再度在体内沸腾，让丢失的梦想再度扬帆起航。'
                    			);
                    			$template = "activity/20160901/index";
                    			$tableName = "20160901_day";
                    	break;

            case '20161124':
                $shareData = array(
                    'title' => '感恩节想说满满的感恩给自己',
                    'desc'  => '感恩节，对爱我的和我爱的人，我们不吝感谢。但此时此刻，还想发自内心地说一句：“谢谢你，亲爱的自己。”'
                );

                $template = "activity/20161124/thx";
                $tableName = "20161124_day";
                break;

            case '201611242':
                $shareData = array(
                    'title' => '感恩节想说满满的感恩给自己',
                    'desc'  => '感恩节，对爱我的和我爱的人，我们不吝感谢。但此时此刻，还想发自内心地说一句：“谢谢你，亲爱的自己。”'
                );

                $template = "activity/201611242/thx";
                //$tableName = "201611242_day";
                break;
    	}
    	$openid = $_W['openid'];
    	$tempId = $_GPC['tempId'];

    	/*$mInfo = pdo_fetch('SELECT  *  FROM ' . tablename($tableName) . ' WHERE openid = :openid', array('openid' => $openid));
    	if($mInfo['id']){
    		if($mInfo['template']){
    			header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityShow&act_id='.$_GPC['act_id'].'&id='.$mInfo['id']);
    		}else{
    			header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityTemp&act_id='.$_GPC['act_id'].'&id='.$mInfo['id']);
    		}
    		exit();
    	}else{*/
    		include $this->template($template);
    	//}
    }
    //照片活动 展示
    public function doMobileActivityShow(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$id = $_GPC['id'];
    	$act_id = $_GPC['act_id'];
    	switch( $act_id ){
    		case '20160624':
    			$shareData = array(
    			'title' => '我参加了比基尼宝贝秀，好羞涩⁄(⁄ ⁄•⁄ω⁄•⁄ ⁄)⁄',
    			'desc' => '为欧洲杯助阵我拼了，快点进来给个赞'
    					);
    					$template = "activity/20160624/";
    					$tableName = "0624_day";
    					$vtableName = "0624_day_vote";
    					break;
    			case '20160714':
    				$shareData = array(
    					'title' => '“土妞”变成“洋妹子”我的成长',
    					'desc' => '所谓女大十八变，越变越漂亮，小伙伴有没有被吓到'
    					);
    				$template = "activity/20160714/";
    				$tableName = "20160714_day";
    				$vtableName = "20160714_day_vote";
    			break;
    			case '20160715':
    				$shareData = array(
    				'title' => '学霸，你录取通知书到了',
    				'desc' => '制作录取通知书，领红包助学金。带你装X带你飞。'
    						);
    						$template = "activity/20160715/";
    						$tableName = "20160715_day";
    						$vtableName = "20160715_day_vote";
    			break;
    			case '20160901':
    				$shareData = array(
    				'title' => '谁是演唱会“潮装达人”？  摇滚的夜晚  够潮才对',
    				'desc' => '上传照片，用潮范儿展现你青春的一面，重获力量，让青春的血液再度在体内沸腾，让丢失的梦想再度扬帆起航。'
    						);
    						$template = "activity/20160901/";
    						$tableName = "20160901_day";
    						$vtableName = "20160901_day_vote";
    			break;

            case '20161124':

                $shareData = array(
                    'title' => '',
                    'desc'  => ''
                );
                $template = "activity/20161124/thx";
                $tableName = "20161124_day";

                break;
    	}
    	$mInfo = pdo_fetch('SELECT  *  FROM ' . tablename($tableName) . ' WHERE id = :id', array('id' => $id));
    	//$mInfo['date'] = date("Y年m月d日",$mInfo['create_time']);
    	if($mInfo['id'] && $mInfo['template'] ){
    		//$vInfo = pdo_fetch('SELECT  count(*) as total  FROM ' . tablename($vtableName) . ' WHERE aid = :aid', array('aid' => $id));
    		//include $this->template($template.$mInfo['template']."/".$mInfo['template']);
    		include $this->template($template);
    	}else{
    		header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityIndex&act_id='.$_GPC['act_id']);
    		exit();
    	}
    }
    //照片活动 保存 发送给朋友
    public function doMobileActivityFin(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$act_id = $_GPC['act_id'];
    	switch( $act_id ){
    		case '20160624':
    			$shareData = array(
    				'title' => '那些不好意思对TA说的话，让我们来帮你',
    				'desc' => '点我写下要说的话，悄悄地告诉TA吧。'
    				);
    			$template = "activity/20160624/";
    			$tableName = "0624_day";
    			break;
    	}
    	$mInfo = pdo_fetch('SELECT  *  FROM ' . tablename($tableName) . ' WHERE openid = :openid', array('openid' => $openid));
    	if($mInfo['id']){
    		if($mInfo['template']){
    			include $this->template($template.$mInfo['template']."/".$mInfo['template']);
    		}else{
    			header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityTemp&act_id='.$_GPC['act_id'].'&id='.$mInfo['id']);
    		}
    	}else{
    		header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityIndex&act_id='.$_GPC['act_id']);
    		exit();
    	}
    }



    //照片活动 选择模版
    public function doMobileActivityTemp(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$act_id = $_GPC['act_id'];
    	$id = $_GPC['id'];
    	switch( $act_id ){
    		case '20160624':
    			$shareData = array(
    			'title' => '那些不好意思对TA说的话，让我们来帮你',
    			'desc' => '点我写下要说的话，悄悄地告诉TA吧。'
    					);
    					$template = "activity/20160624/templateSelect";
    					$tableName = "0624_day";
    					break;
    	}
    	$mInfo = pdo_fetch('SELECT  *  FROM ' . tablename($tableName) . ' WHERE openid = :openid', array('openid' => $openid));
    	if($mInfo['id']){
    		if($mInfo['template']){
    			header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityShow&act_id='.$_GPC['act_id'].'&id='.$mInfo['id']);
    		}else{
    			include $this->template($template);
    		}
    	}else{
    		header('location: '.$_W['siteroot'].'app/index.php?i=4&c=entry&m=member&do=ActivityIndex&act_id='.$_GPC['act_id']);
    		exit();
    	}
    }
    //照片活动 投票
    public function doMobileActivityVote(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$act_id = $_GPC['act_id'];
    	$id = $_GPC['id'];
    	if( !$openid || !$act_id || !$id ){
    		echo  json_encode(array('status'=>0,'msg'=>'对不起，请使用微信APP支持或按照规则进行！'));
    		exit();
    	}

    	switch( $act_id ){
    		case '20160624':
    				$template = "activity/20160624/vote";
    				$tableName = "0624_day_vote";
    				if( date('Ymd') > "20160629" ){
    					echo  json_encode(array('status'=>0,'msg'=>'对不起，支持活动已结束！'));
    					exit();
    				}
    		break;
    		case '20160714':
    			$template = "activity/20160714/vote";
    			$tableName = "20160714_day_vote";
    			if( date('Ymd') > "20160713" ){
    				echo  json_encode(array('status'=>0,'msg'=>'对不起，支持活动已结束！'));
    				exit();
    			}
    			break;
    		case '20160715':
    				$template = "activity/20160715/vote";
    				$tableName = "20160715_day_vote";
    				if( date('Ymd') > "20160715" ){
    					echo  json_encode(array('status'=>0,'msg'=>'对不起，支持活动已结束！'));
    					exit();
    				}
    				break;
    	}
    	$mInfo = pdo_fetch('SELECT  *  FROM ' . tablename($tableName) . ' WHERE openid = :openid and act_id = :act_id and aid = :aid ', array('openid' => $openid,'act_id'=>$act_id,'aid'=>$id));
    	if($mInfo['id']){
    		echo  json_encode(array('status'=>0,'msg'=>'对不起，一人只能支持一次！'));
    		exit();
    	}else{
    		$user_IP = ($_SERVER["HTTP_VIA"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : $_SERVER["REMOTE_ADDR"];
    		$voteData = array(
    			'openid' => $openid,
    			'act_id'  => $act_id,
    			'aid'	=> $id,
    			'userIP' => $user_IP,
    			'create_time' => time()
    		);
    		$rs = pdo_insert($tableName,$voteData);
    		$inid = pdo_insertid();

    		if($inid){
    			echo json_encode(array('status'=>1,'id'=>$id,'act_id'=>$_GPC['act_id']));
    		}else{
    			echo json_encode(array('status'=>0,'msg'=>'支持失败，请再试一次！'));
    		}
    	}
    }
    //照片活动 排行
    public function doMobileActivityList(){
    	global $_W,$_GPC;
    	$act_id = $_GPC['act_id'];
    	switch( $act_id ){
    		case '20160624':
    			$template = "activity/20160624/ranking";
    			$voteTableName = "0624_day_vote";
    			$tableName = "0624_day";
    			$shareData = array(
    					'title' => '一大波比基尼宝贝胸涌来袭',
    					'desc' => '燃情欧洲杯，比基尼与足球更配哦'
    			);
    			break;
    		case '20160714':
    				$template = "activity/20160714/ranking";
    				$voteTableName = "20160714_day_vote";
    				$tableName = "20160714_day";
    				$shareData = array(
    						'title' => '“土妞”变成“洋妹子”我的成长',
    						'desc' => ''
    				);
    				break;
    	}
    	$topArr = pdo_fetchall( " select aid,count(*) as total from ". tablename($voteTableName) . "  where act_id = :act_id  group by aid order by total desc limit 0,50  ", array("act_id"=>$act_id) );
    	foreach($topArr as $k => $v){
    		$topId[] = $v['aid'];
    		$topVote[$v['aid']] = $v['total'];

    	}
    	if(count($topVote)){
    		$topAct = pdo_fetchall(" select * from " . tablename($tableName) . " where id in (".implode(",", $topId).")  ");
    		foreach($topAct as $k => $v){
    			$v['vote'] = $topVote[$v['id']];
    			$voteArr[] = $topVote[$v['id']];
    			$topInfo[] = $v;
    		}
    		array_multisort($voteArr, SORT_DESC, $topInfo);
    		$i = 1;
    		foreach($topInfo as $k => $v){
   				$topInfo[$k]['order'] = $i;
    			$i++;
    		}
    		include $this->template($template);
    	}else{
    		header('location: '.$this->createMobileUrl('tips',array('type'=>'x','title'=>'结果还不能查看','msg'=>'还没有选手参加！')));
    		exit();
    	}
    }
    //照片活动 精选显示
    public function doMobileActivitySelected(){
    	global $_W,$_GPC;
    	$act_id = $_GPC['act_id'];
    	switch( $act_id ){
    		case '20160901':
    			$template = "activity/20160901/ranking";
    			$tableName = "20160901_day";
    			$shareData = array(
    				'title' => '谁是演唱会“潮装达人”？  摇滚的夜晚  够潮才对',
    				'desc' => '上传照片，用潮范儿展现你青春的一面，重获力量，让青春的血液再度在体内沸腾，让丢失的梦想再度扬帆起航。'
    			);
    			break;
    	}
    	$topInfo = pdo_fetchall(" select * from " . tablename($tableName) . " where status = 1 order by id desc  limit 0,50 ");
    	include $this->template($template);
    }


	public function createDir($dir){
		return is_dir($dir) or ($this->createDir(dirname($dir)) and @mkdir($dir, 0777));
	}


    function prepareJSON($input){
    	$imput = mb_convert_encoding($input,'UTF-8','ASCII,UTF-8,ISO-8859-1');
    	if(substr($input,0,3) == pack("CCC",0xEF,0xBB,0xBF)) $input = substr($input,3);
    	return $input;
    }
	/*
	 *  3周年店庆 领券
	 */
    public function doMobileHaveCard(){
    	global $_W,$_GPC;

    	$snStart = date('ymd');
    	if ($_W['ispost'] && $_W['isajax']) {
    		// 获取流水信息
    		$orderId = $_GPC['retailId'];
    		//logs($orderId);

    		$errorTotal = pdo_fetchcolumn(" select count(*) from card_error_log where openid = '".$_W['openid']."' ");
    		if($errorTotal >= 8){
    			echo json_encode(array('error' => '对不起，您的错误次数过多，请您到服务台领取现金券！ ', 'status' => 0));
    			exit();
    		}


    		if( date('ymd') < '161223' ){
    			echo json_encode(array('error'=>'对不起，活动未开始。','status'=>0));exit();
    		}
    		if( date('ymd') > '170102' ){
    			echo json_encode(array('error'=>'对不起，活动已结束。','status'=>0));exit();
    		}


            if (date('ymd') != substr($orderId,0,6)) {
    			echo json_encode(array('error'=>'对不起，只限当日小票参加活动','status'=>0));exit();
    		}



    		$cardIdArr = array("221");
    		//判断重复领取
    		$openidTotal = pdo_fetchcolumn(" select count(*) as total  from card_card_card where card_id in (".implode(",",$cardIdArr).") and openid = '".$_W['openid']."' ");
    		if($openidTotal >= 1){
    			echo json_encode(array('error' => '对不起，您的微信号已经领取过现金券了！', 'status' => 0));
    			exit();
    		}
    		$orderTotal = pdo_fetchcolumn(" select password from card_card_card where card_id in (".implode(",",$cardIdArr).") and  orderid = '".$orderId."' ");
    		if($orderTotal){
    			echo json_encode(array('error' => '对不起，您的小票已经领取过现金券了！', 'status' => 0));
    			exit();
    		}

    		$order = file_get_contents("http://192.168.0.110/checkOrder.php?orderId=".$orderId."&type=".$_GPC['type']);
    		$order = $this->prepareJSON( $order );
    		$orderArr = json_decode($order);
    		if($orderArr->status){
    			$result['status'] = "1";
    			$result['brand'] = $this->getDeptName( $orderArr->c_dept_id );
    			$result['c_dept_id'] = $orderArr->c_dept_id;
    			$result['dept_amount'] = "".$orderArr->dept_amount."";
    			$result['orderPrice'] = "".$orderArr->orderPrice."";
    			$result['ap_total'] = $orderArr->apTotal;
    			$result['orderid'] = $orderId;
    			$mobile = '';
    			if ($_W['fans']['follow'] && $_W['fans']['uid']) {
    				$mobile = $_W['member']['mobile'];
    			}
    			$result['tel'] = $mobile;
    			$rs =  json_encode($result);
    			echo $rs;
    			exit();
    		}else{
    			$result = array("status"=>"0","error"=>$orderArr->msg );
    			echo json_encode($result);
    			exit();
    		}
    	} else {
    		include $this->template('member/haveCard_dior');
    	}
    }
	/*
	 *  3周年店庆 领券 前端测试
	 */
    public function doMobileHaveCard_test(){
		global $_W,$_GPC;
		$snStart = date('ymd');
		if ($_W['ispost'] && $_W['isajax']) {
			$result = array("status"=>"0","error"=>$_GPC['retailId'] );
    		echo json_encode($result);
    		exit();
		}
		include $this->template('member/haveCard_test');
	}
    /*
     *  发放卡券
     */
     public function doMobilepushCard(){
    	global $_W,$_GPC;

    	if ($_W['ispost'] && $_W['isajax']) {
    		$cardIdArr = array("221");
    		$data = array(
    				'dept_amount'      => $_GPC['dept_amount'],
    				'tel'        => $_GPC['tel'],
    				'openid'     => $_W['openid'],
    				'orderid'    => $_GPC['orderid'],
    				'c_dept_id'  => $_GPC['c_dept_id'],
    				'brand'      => $_GPC['brand'],
    				'orderPrice' => $_GPC['orderPrice'],
    				'ap_total'   => $_GPC['ap_total'],
    				'status'     => 2,
    				'r_way'      => 2
    		);
    		//判断重复领取
    		$openidTotal = pdo_fetchcolumn(" select count(*) as total  from card_card_card where card_id in (".implode(",",$cardIdArr).") and openid = '".$data['openid']."' ");
    		if($openidTotal >= 1){
    			echo json_encode(array('msg' => '对不起，您的微信号已经领取过现金券了！', 'status' => 0));
    			exit();
    		}
    		$telTotal = pdo_fetchcolumn(" select count(*) as total from card_card_card where card_id in (".implode(",",$cardIdArr).") and tel = '".$data['tel']."' ");
    		if($telTotal >= 1){
    			echo json_encode(array('msg' => '对不起，您的手机号已经领取过现金券了！', 'status' => 0));
    			exit();
    		}
    		$orderTotal = pdo_fetchcolumn(" select password from card_card_card where card_id in (".implode(",",$cardIdArr).") and  orderid = '".$data['orderid']."' ");
    		if($orderTotal){
    			echo json_encode(array('msg' => '对不起，您的小票已经领取过现金券了！', 'status' => 0));
    			exit();
    		}


    		$cardTotal = pdo_fetchcolumn(" select password from card_card_card where status = 1 and card_id = '".$cardIdArr[0]."' ");
    		if($cardTotal){
	    		// 领卡
	    		try{
	    			// 开启事务
	    			pdo_begin();
	    			foreach ($cardIdArr as $k => $v){
	    				$data['card_id'] = $v;
	    				$data['price'] = intval($data['dept_amount'] / 1000) * 100;
	    				/*if($data['price'] > 1000 ){
	    					$data['price'] = 1000;
	    				}*/
	    				$result = pdo_query("update card_card_card set price = :price, tel = :tel, openid = :openid , orderid = :orderid , c_dept_id = :c_dept_id , brand = :brand ,orderPrice = :orderPrice ,status = :status, r_way = :r_way ,dept_amount = :dept_amount , ap_total = :ap_total where card_id = :card_id limit 1 ",$data);
	    				if ($result === false) {
	    					throw new Exception('发卡失败，现金券编号：'.$v.' Openid: '.$_W['openid']."  data:".implode(" ",$data)."  card_id:".$v);
	    				}
	    			}
	    			//提交
	    			pdo_commit();
	    		}catch (Exception $e) {
	    			logs($e->getMessage(),'sendCard');
	    			pdo_rollback();
	    			echo json_encode(array('msg' => 'Oh..好像出了点问题，请稍后试一下', 'status' => 0));
	    			exit();
	    		}
	    		$cardArr = pdo_fetchall(" select  password  from  card_card_card where openid = :openid and card_id in ( ".implode(",",$cardIdArr)." ) ",array("openid"=>$data['openid']));
	    		foreach($cardArr as $k => $v){
	    			$passwords .= $v['password']." ";
	    		}
	    		$acc = WeAccount::create(4);
	    		$temData = array(
	    				'first'    => array(
	    						'value' => "恭喜您成功领取现金券。\n",
	    						'color' => '#000000'
	    				),
	    				'keyword1' => array(
	    						'value' => 'Dior 现金券',
	    						'color' => '#69008C'
	    				),
	    				'keyword2' => array(
	    						'value' => $passwords,
	    						'color' => '#69008C'
	    				),
	    				'keyword3' => array(
	    						'value' => "2016-12-23",
	    						'color' => '#69008C'
	    				),
	    				'keyword4' => array(
	    						'value' => "2017-01-02\n",
	    						'color' => '#69008C'
	    				),
	    				'remark'   => array(
	    						'value' => "如有疑问，请拨打客服电话：029-86300000 \n 点击详情，查看已领取的现金券。",
	    						'color' => '#000000'
	    				),
	    		);
	    		$aa = $acc->sendTplNotice($_W['openid'],'NeJT0MzlrbnEO9fL0x5zmTXzbUeq5vQCyGCtti_p52E',$temData,$_W['siteroot'].'app/index.php?i=4&c=entry&do=coupons&m=member','#FF683F');
	    		echo json_encode(array('msg' => '领取成功', 'status' => 1,'password'=>$passwords));
	    		exit();
    		}else{
    			echo json_encode(array('msg' => '现金券库存不足', 'status' => 0));
    			exit();
    		}
    	}
    }
    /*
     *  记录错误日志
     */
    public function doMobilesaveError(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$error = $_GPC['error'];
    	$data = array(
    			"openid" => $openid,
    			"error" => $error,
    			"create_time" => date("Y-m-d H:i:s"),
    			"ipAddress" => $_SERVER['REMOTE_ADDR']
    			);
    	$rs = pdo_insert_table("card_error_log",$data);
    	if($rs){
    		echo json_encode(array('msg' => '成功', 'status' => 1));
	    	exit();
    	}else{
    		echo json_encode(array('msg' => '失败', 'status' => 0));
    		exit();
    	}
    }
    /*
     *  返回部组名称
     */
    private function getDeptName($c_dept_id){
    	global $_W,$_GPC;
    	$rs = pdo_fetch("select * from card_dept_name where c_dept_id = :c_dept_id ",array("c_dept_id"=>$c_dept_id));
    	if($rs){
    		return $rs['c_dept_name'];
    	}else{
    		return $c_dept_id;
    	}
    }



    /*
       模板消息发送
     * 飞凡模板消息通知
     */
    public function msgSender($openid,$realname,$nickname, $nonceStr)
    {

        $time = date("Y-m-d H:i:s");
        // 受理任务模板消息数据准备
        $postdata = array(
            // 发送者数据暂定    生产环境为  $touser
            'touser'      => $openid,
            // 模板消息暂定      生产环境下为实际模板
            'template_id' => 'lygwXNh49hgy2II2SjFdI7lkikD3yTAW15WkkFcpR74',
            // 消息暂定
            'topcolor'    => '#69008C',
            //  会员权益详情页面
            'data'=> array(
                'first' => array(
                    'value' => "尊敬的赛格国际会员 {$realname}，恭喜您成功预约鲸喜欢乐购活动",
                    'color' => '#173177',
                ),
                'keyword1' => array(
                    'value' => $nickname,
                    'color' => '#000000',
                ),
                'keyword2' => array(
                    'value' => $nonceStr,
                    'color' => '#173177',
                ),
                'keyword3' => array(
                    'value' => '此号码当天有效',
                    'color' => '#173177',
                ),
                'keyword4' => array(
                    'value' => $time,
                    'color' => '#173177',
                ),
                'remark' => array(
                    'value' => '如有疑问请咨询: 86300000',
                    'color' => '#99009',
                )
            )
        );

        $postdata = json_encode($postdata);
        $token = $this->getToken();

        $retinfo = $this->http_post_attach('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$token, $postdata);

        $msgNotice = json_decode($retinfo);
        return get_object_vars($msgNotice);
    }

    public function doMobileBenefit()
    {
        include $this->template('member/memberBenefits');
    }


    public function doMobileCURLFES()
    {
        $oCurl = curl_init();

        $param=array(
            'custId'=>'20141216160257722',
        );
        $str = json_encode($param);

        $url='http://sapi.cnsaga.com:8066/?service=Customer.GetCustScore';

        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $str);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);


        var_dump($sContent);
    }

    /**
     * @return string
     * 返回Api接口调用地址
     * Example:http://192.168.0.20/?service=Customer.GetCustScore
     */
    public function returnApiHost(){
        return 'http://192.168.0.20/';
    }

    /**
     * 模板消息辅助发送
     */
    public function http_post_attach($url,$param)
    {
////        $url='http://sapi.cnsaga.com:8066/?service=Customer.GetCustScore';
//        $url='http://192.168.0.20/?service=Customer.GetCustScore';
//
//
//        $param=array(
//            "custId"=>'20141216160257722',
//        );

        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }

        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);

        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);

        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);

//            var_dump($sContent);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }
    /*
     *  导购登记 店面列表页
     */
    public function doMobileshopList(){
    	global $_W,$_GPC;
    	$c_dept_id = $_GPC['c_dept_id'];
    	$c_dept_name = $_GPC['c_dept_name'];
    	$where = array();
    	if($c_dept_id){
    		$where[] = " c_dept_id = '".$c_dept_id."' ";
    	}
    	if($c_dept_name){
    		$where[] = " c_dept_name like '%".$c_dept_name."%' ";
    	}
    	if($c_dept_id || $c_dept_name){
    		$sql = " select * from card_dept_name ".( count($where) > 0 ? ' WHERE '.implode(" AND ",$where) : "" )." order by c_dept_id ASC ";
    		$deptArr = pdo_fetchall($sql);
    		foreach($deptArr as $k => $v){
    			$deptArr[$k]['url'] = urlencode("http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=shopAddEmp&m=member&c_dept_id=".$v['c_dept_id']);
    		}
    	}
    	include $this->template('member/deptList');
    }
    /*
     *  导购登记 表单页
     */
    public function doMobileshopAddEmp(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$c_dept_id = $_GPC['c_dept_id'];

    	$sql = " select * from card_dept_name where c_dept_id = '".$c_dept_id."' ";
    	$deptArr = pdo_fetch($sql);

    	$sql = " select * from ims_shop_emp as a ,card_dept_name as b where a.c_dept_id = b.c_dept_id and openid = '".$openid."' ";
    	$rs = pdo_fetch($sql);

    	include $this->template('member/shopAddEmp');
    }
    /*
     *  导购登记 保存
    */
    public function doMobilesaveShopEmp(){
    	global $_W,$_GPC;
    	$openid = $_W['openid'];
    	$c_dept_id = $_GPC['c_dept_id'];
    	$tel = $_GPC['tel'];
    	$name = $_GPC['name'];
    	$create_time = date('Y-m-d H:i:s');
    	if(!$openid){
    		echo "<script>alert('登记失败，请在微信中填写！');location='http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=shopAddEmp&m=member&c_dept_id=".$c_dept_id."'</script>";
    		exit();
    	}
    	$data = array(
    			'openid' => $openid,
    			'c_dept_id' => $c_dept_id,
    			'create_time' => $create_time,
    			'tel' => $tel,
    			'name'=>$name,
    			'status' => 1
    			);
    	$result = pdo_insert("shop_emp", $data);
    	$id     = pdo_insertid();
    	if($id){
    		echo "<script>alert('登记成功，您还可以将此页面分享给小伙伴进行登记！');location='http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=shopAddEmp&m=member&c_dept_id=".$c_dept_id."'</script>";
    		exit();
    	}else{
    		echo "<script>alert('登记失败！');location='http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=shopAddEmp&m=member&c_dept_id=".$c_dept_id."'</script>";
    		exit();
    	}
    }

    /**
     * 店庆分享页面
     */
    public function doMobileCelebrate()
    {
        global $_W, $_GPC;

        include $this->template("member/celebrate");
    }



    public function doMobilePlateinfos(){

        $plateNumber = '陕A9WZ80';

        $server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
        $username ="sa";  //用户名
        $pwd ="Lf0507"; //密码
        $database ="ACS_Parking20000";  //数据库名称

        //进行数据库连接
        $conn = mssql_connect($server,$username,$pwd) or die ("connect failed");
        mssql_select_db($database,$conn);
        //查询入场记录
        $sql = "SELECT TOP 1  *, convert(varchar(19),Crdtm,121) AS inTime FROM Tc_UserCrdtm_In WHERE CarCode = '".$plateNumber."' ORDER BY Crdtm DESC";
        $sql = iconv('utf-8', 'gb2312', $sql);
        $rs = mssql_query($sql);
        $row = mssql_fetch_assoc($rs);

        $date = strtotime($row['inTime']);
        //$inTime = $row['inTime'];
        $leaveCharge = pdo_fetch("SELECT `platenumber`,`leave_time`,`amount` FROM `ims_park_leave` WHERE `platenumber` ='".$platenumber."' AND (`create_time` BETWEEN ".$date." AND ".time()." )");

        if($leaveCharge){
            $inTime = $row['inTime'];
            //计算停车信息
            $entryTime =  round((time() - strtotime($row['inTime'])) / 3600,1);
            $entryTime = round($entryTime,0);
            $getCardInfo = array(
                "data" => array(
                    "amount"  => $entryTime * 3,
                    "carNum" => iconv('gb2312', 'utf-8',$row['CarCode']),
                    "carPhoto" => "http://113.140.80.194:8088/img/". iconv('gb2312', 'utf-8', str_replace("\\","/",$row['Carimage'])),
                    "entryTime" => $row['inTime'],
                    "leaveTime" => date("Y-m-d H:i:s"),
                    "time" => date("Y-m-d H:i:s"),
                    "isLeave" => 0
                ) );

           //查询结算记录
            $outTime = $this->getPlateNumberOutTime($plateNumber, $inTime);
            if($outTime['OutTime']){
                //已离场
                if($outTime['OutTime'] == $getCardInfo['data']['entryTime']){
                    //用户已离场
                    $isInMsg = "您的爱车已于，".$outTime['OutTime']."驶离停车场，欢迎再次光临！";
                    header('location: '.$this->createMobileUrl('selectPlateNumber',array('msg'=> $isInMsg,"floor"=>$_GPC['floor'],"zone"=>$_GPC['zone'])));
                    exit();
                }else{
                    $entryTime =  round((time() - strtotime($outTime['OutTime'])) / 3600,1);
                    $entryTime = round($entryTime,0);
                    $getCardInfo['data']['amount'] = $entryTime * 3;
                    $getCardInfo['data']['entryTime'] = $outTime['OutTime'];
                    $tipsArr['title'] = $outTime['OutTime']." 已缴纳停车费 ".$outTime['ChargeMoney']."元，感谢您的光临，欢迎下次光临！";
                    $tipsArr['url'] = "";
                }
            }



        }else{
            echo 'without';
        }
    }


    /**
     * 停车调查问卷
     */
    public function doMobileSurvey()
    {
        global $_W,$_GPC;
        $openid = $_W['openid'];

        $uid = mc_openid2uid($openid);

        //session_start();
        //$token = md5(uniqid(rand(), TRUE));

        if(isset($_GET['token'])){
            $token = $_GET['token'];
        }

        /**
         * 数据加载完成之后
         */

        if($_SERVER['REQUEST_METHOD'] == 'POST'){

            // 防止Token重复提交
            //$tokenInfo = pdo_fetch("SELECT * FROM `ims_park_test_result` WHERE `token`= '".$token."'");

            //var_dump($tokenInfo);
            /**
            /**
            if(!empty($tokenInfo)){
                $msg['status'] = 0;
                $msg['desc']   = '请勿重复提交';
                echo json_encode($msg);
                exit();
            }*/


            if($uid){
                $data  = $_POST;
                $data['Advice'] = htmlspecialchars($data['Advice']);

                $basicInfo = pdo_fetch("SELECT `mobile`,`platenumber` FROM `ims_mc_members` WHERE `uid`= ".$uid);

                $allowNames = pdo_fetch("SELECT `mobile`, `platenumber` FROM `ims_mc_members` WHERE `platenumber`='".$basicInfo['platenumber']."'");


                if(empty($allowNames)){
                    $msg['status'] = 0;
                    $msg['desc']   = '您尚无参与调研的权限';
                    echo json_encode($msg);
                    exit();
                }



                //var_dump($basicInfo);
                //exit();
                 $status = pdo_insert('park_test_result',
                     array(
                         'platenumber'=>$basicInfo['platenumber'],
                         'mobile'=>$basicInfo['mobile'],
                         'isReceive'=>$data['isReceive'],
                         'isSufficient'=>$data['isSufficient'],
                         'isCorrect'=> $data['isCorrect'],
                         'Advice'=>$data['Advice'],
                         //'token' => $token,
                         'create_time'=>time()));

                //$status = pdo_query($query);
                $returnInfo['status'] = 1;
                $returnInfo['desc'] = '感谢您的参与！';

                if($status){
                    /**
                     * 自动添加1000积分
                     */
                    //$currentParkScore = pdo_fetch("SELECT `score` FROM `ims_park_member` WHERE `uid`=".$uid);
                    //$updateScore      = $currentParkScore['score'] + 1000;
                    //$updateRet        = pdo_update('park_member',array('score'=>$updateScore), array('uid'=>$uid));

                    echo json_encode($returnInfo);
                    exit();
                }else{
                    $returnInfo['status'] = 0;
                    $returnInfo['desc'] = '通讯异常';
                    echo json_encode($returnInfo);
                    exit();
                }
            }else{
                echo '{"status":0,"desc":"您的会员信息有误"}';
                exit();
            }
        }else{
            include $this->template('member/survey');
        }
    }


    /**
     * 飞凡领取劵码如果汇总资格总数小于 2000
     * @step-1 如果汇总资格总数小于 2000, 存在领取凭证资格
     * @step-2 如果没人每天推送次数小于等于 3 则推送凭据
     */
    public function doMobileffanSend()
    {
        //如果汇总资格总数小于 2000
        /**
         * @step-1 如果汇总资格总数小于 2000, 存在领取凭证资格
         * @step-2 如果没人每天推送次数小于等于 3 则推送凭据
         */

        /**
         * 数据信息表 ims_ffan_credit_info
         *
         * id
         * mobile
         * realname
         * openid
         * idcard
         * ffan_credit
         * create_time
         */

        /**
         * @step-0 判断点开链接的会员信息openid 是否是会员
         * 如果不存在是会员信息的话, 点击注册信息按钮
         */

        global $_W;

        $openid = $_W['openid'];

        $uid = mc_openid2uid($openid);

        // 查询数据资格总数如果小于 2000 ,则存在飞凡劵码领取资格
        // 如果当天范围内，根据 Openid 查询领卡数据
        // 如果存在发劵数据小于 3 张的话推送
        // 如果推送成功的话


        if($uid){
            // 查询飞凡会员信息
            $credit_sum = pdo_fetch("SELECT COUNT(*) AS SUM FROM `ims_ffan_credit_info`");

            if($credit_sum['SUM'] < 2000){
                /**
                 * 当天的起止时间
                 */
                $day_start = mktime(0,0,0,date("m"),date("d"),date("Y"));
                $day_end   = mktime(23,59,59,date("m"),date("d"),date("Y"));

                //
                $ffan_credit_info = pdo_fetch("SELECT COUNT(*) AS SUM FROM `ims_ffan_credit_info` WHERE `openid`='".$openid."' AND (`create_time` BETWEEN ".$day_start." AND ".$day_end.")");

                if($ffan_credit_info['SUM'] < 3){
                    // 推送数据
                    $serial = "1X0123456789";
                    $serailset = date("Ymd");
                    $serailset = substr($serailset, 2);

                    $nonceStr = '';
                    for($i = 0; $i < 6;$i++){
                        $nonceStr .= substr($serial, mt_rand(0, strlen($serial)-1),1);
                    }

                    $customerData = pdo_fetch("SELECT `realname`,`mobile`,`idcard` FROM `ims_ffan_credit_info` WHERE `openid`='".$openid."'");
                    if(!empty($customerData)){

                        $nickname = $_W['nickname'];
                        $sendRet = $this->msgSender($openid, $customerData['realname'],$nickname, $serailset);

                        if($sendRet['errcode'] == 0){
                            // 发送数据成功之后
                            $customerData['openid']      = $openid;
                            $customerData['create_time'] = time();
                            $customerData['ffan_credit'] = $nonceStr;
                            $insert_Info = pdo_insert('ims_ffan_credit_info', $customerData);

                        }

                        /*if($insert_Info){
                            // 数据信息发送成功
                        }else{
                            //
                        }*/
                    }else{
                        // 会员信息数据有误咨询一楼服务台
                        echo "<scrpit>alert('你还未注册赛格国际会员')</script>";
                    }
                }else{
                    header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=sendTipsInfo&m=member&Type=2");
                }
            }else{
                // 飞凡领取的资格数量已满
                header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=sendTipsInfo&m=member&Type=1");
            }
        }else{
            // 先注册赛格会员信息数据
            // tips
            echo "<scrpit>alert('你还未注册赛格国际会员')</script>";
            header("location:http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=userRegist&m=member");
        }
    }



    /**
     * B2 招商餐饮开店
     */
    public function doMobilebasementInfo()
    {
        global $_W;

        $openId = $_W['openid'];
        $uid = mc_openid2uid($openId);
        include $this->template('member/basementInfo');
    }



    /**
     * Specialist For guaranteed personal
     * @param $searchPlateNumber
     * @return bool
     */
    protected function bannedList($searchPlateNumber)
    {
        $guarantedList = array('陕AV0G77');

        if(in_array($searchPlateNumber, $guarantedList)){
            return false;
        }else{
            return true;
        }
    }



    /**
     * @param $plateNumber
     * @return bool
     */
    protected function lastAnyPlateNumberEntryInfo($plateNumber)
    {
        $server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
        $username ="sa";  //用户名
        $pwd ="Lf0507"; //密码
        $database ="ACS_Parking20000";  //数据库名称

        //进行数据库连接
        $conn = mssql_connect($server,$username,$pwd) or die ("connect failed");
        mssql_select_db($database,$conn);
        //查询入场记录
        $sql = "SELECT  *,convert(varchar(19),Crdtm,121) AS inTime  FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' and Crdtm between cast('".date("Y-m-d")." 00:00:00' as datetime) and cast('".date("Y-m-d")." 23:59:59' as datetime)";
        $sql = iconv('utf-8', 'gb2312', $sql);
        $rs = mssql_query($sql);
        $row = mssql_fetch_assoc($rs);
        if($row['inTime'])
        {
            return $row['inTime'];
        }else{
            return false;
        }
    }

    /**
     * @param $plateNumber
     * @return bool
     */
    public function doMobileLastAnyEntryInfo()
    {
        $plateNumber = '陕A70F82';
        $server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
        $username ="sa";  //用户名
        $pwd ="Lf0507"; //密码
        $database ="ACS_Parking20000";  //数据库名称

        //进行数据库连接
        $conn = mssql_connect($server,$username,$pwd) or die ("connect failed");
        mssql_select_db($database,$conn);

        var_dump($conn);
        //查询入场记录
        //$sql = "SELECT  *,convert(varchar(19),Crdtm,121) AS inTime  FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' and Crdtm between cast('".date("Y-m-d")." 00:00:00' as datetime) and cast('".date("Y-m-d")." 23:59:59' as datetime)";
        $sql = "SELECT  *,convert(varchar(19),Crdtm,121) AS inTime  FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."'";

        echo $sql;

        $sql = iconv('utf-8', 'gb2312', $sql);
        $rs = mssql_query($sql);
        $row = mssql_fetch_assoc($rs);


        var_dump($row);
        if($row['inTime'])
        {
            return $row['inTime'];
        }else{
            return false;
        }
    }



    /**
     * @param $platenumber
     * @return mixed
     */
    protected function afterBindPlaterNumberStatus($platenumber)
    {
        // 最后一条入场记录，和当前时间范围内
        $lastEntryRecord = $this->lastAnyPlateNumberEntryInfo($platenumber);

        $currentTime = date("Y-m-d H:i:s");

        // 在该时间范围内如果存在该车辆的话，发送消息
        //return $status;
        if($lastEntryRecord){
            $parkInfo = pdo_fetch("SELECT `parkno` FROM `ims_parkinfo_status` WHERE `platenumber` ='".$platenumber."' AND (`create_time` BETWEEN ".$lastEntryRecord." AND ".$currentTime.") ORDER BY `create_time` DESC LIMIT 1");

            if($parkInfo['parkno']){
                return $parkInfo['parkno'];
            }
        }else{
            return false;
        }
    }

    /**
     *
     */
    public function doMobileLastEntryInfo()
    {
        $server = "paypark";
        $username = "sa";
        $password = "Lf0507";
        $database = "ACS_Parking20000";

        $connect = mssql_connect($server, $username, $password) or die(mssql_get_last_message());
        mssql_select_db($database, $connect);

        $ACS_SQL = "SELECT  COUNT(*) AS sumcount FROM Tc_UserCrdtm_In where Crdtm between cast('".date("Y-m-d")." 00:00:00' as datetime) and cast('".date("Y-m-d")." 23:59:59' as datetime) ";
        $ACS_SQL = iconv('utf-8', 'gb2312', $ACS_SQL);
        $rs = mssql_query($ACS_SQL);
        $row = mssql_fetch_assoc($rs);

        $sum_entry = $row['sumcount'];

        echo $sum_entry;
    }

	// http://wx.cnsaga.com/app/index.php?i=4&c=entry&do= ..&m=member#wechat

	// +----------------------------------------------------------------------
	// | 小火车 BEGIN daichen
	// +----------------------------------------------------------------------

	/**
	 * 小火车抽奖入口
	 * daichen
	 */
	public function doMobileShowentry(){


		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
		    // 非微信浏览器禁止浏览
		    echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/christmas/images/thief.gif"/>';
		    echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
		} else {


				$sys=pdo_fetch("SELECT sys_on_off FROM ims_act_trainsys");

				if($sys['sys_on_off'] == 0){
						include $this->template('member/trainEnd');
						exit();
//					echo '<img style="margin-left:30%;height:160px;width:160px;display:inline-block" src="../addons/member/template/mobile/christmas/images/thief.gif"/>';
//				    echo "<h4 style='display:inline-block'> :-) 小伙伴们太给力了！ 系统有点忙 ^_^ 请稍后再来！ </h5>";
				}else{




				global $_W;

				//$openId=$_W['openid'];
				/*
				$openId=$_W['openid'];

				$users=pdo_fetch("SELECT openId FROM ims_act_times Where openId='".$openId."'");
				if($openId!='' && isset($openId)){
					//原始抽奖次数配置
					$orginTimes=3;
					//第一次用户进来 插入用户数据
					if(empty($users)){

						$userInfo=array('openId'=>$openId,'times'=>$orginTimes);
				    	$lookresult = pdo_insert('act_times', $userInfo);
					}

				}
				*/


				$from=htmlspecialchars($_GET['sagafrom']);

				if(isset($from) && $from!=''){

					$openId=$_W['openid'];

					$users=pdo_fetch("SELECT openId FROM ims_act_times Where openId='".$openId."'");
					if($openId!='' && isset($openId)){
							//原始抽奖次数配置
							$orginTimes=3;
							//第一次用户进来 插入用户数据
							if(empty($users)){

								$userInfo=array('openId'=>$openId,'times'=>$orginTimes);
						    	$lookresult = pdo_insert('act_times', $userInfo);
							}else{
								//因为业务流程原因 将重置更改到入场就重置
							//拿到用户最后一次抽奖的时间
							$lastTime=pdo_fetch("SELECT lastTime,attend_times FROM ims_act_times Where openId='".$openId."'");

							$time=substr($lastTime['lastTime'],0,strrpos($lastTime['lastTime'],' '));

							//拿到用户隔天参与的次数 累计
							$attend_times=$lastTime['attend_times'];

							//拿到用户最后一次抽奖时间 格式为20161209
							$timeRes=str_replace('-', '',$time);
							//拿到当前时间20161210
							$nowTime=date('Ymd');

							//如果是隔天或者隔更多天，则重置剩余次数为3 变为可以转发
							if($nowTime-$timeRes>=1){
								$reset_data=array(
										'times'=>3,
										'status'=>1,
										'attend_times'=>$attend_times+1
									);
								pdo_update('act_times', $reset_data, array('openId' => $openId));
							}
						}



					}

					switch ($from)
					{
					//来自公众号推广的用户
					case 'gzh':
						
					    //查询此用户信息
						$fromGzh=pdo_fetch("SELECT from_gzh FROM ims_act_times Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromGzh['from_gzh'] == 0){
							//$updateBtnQdj=$btn_enter['btn_enter']+1;
							//数据库更新data
							$from_gzh_data = array(
								    'from_gzh' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('act_times', $from_gzh_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 公众号入口进入的用户   更新 {$openId}",'actlogs');
							}
						}
					break;
					//来自朋友圈推广的用户
					case 'pyq':
						
						include $this->template('member/trainEnd');
						exit();
					    //查询此用户信息
						$fromPyq=pdo_fetch("SELECT from_pyq FROM ims_act_times Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromPyq['from_pyq'] == 0){
							//$updateBtnQdj=$btn_enter['btn_enter']+1;
							//数据库更新data
							$from_pyq_data = array(
								    'from_pyq' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('act_times', $from_pyq_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 通过朋友圈推广进来的用户    更新 {$openId}",'actlogs');
							}
						}
					  break;
					//来自朋友圈分享带来的用户
					case 'pyqshare':
					  //查询此用户信息
						$fromPyqShare=pdo_fetch("SELECT from_pyq_share FROM ims_act_times Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromPyqShare['from_pyq_share'] == 0){
							//$updateBtnQdj=$btn_enter['btn_enter']+1;
							//数据库更新data
							$from_pyq_share_data = array(
								    'from_pyq_share' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('act_times', $from_pyq_share_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 通过朋友圈推广进来的用户    更新 {$openId}",'actlogs');
							}
						}
					  break;
					//来自朋友分享带来的用户
					case 'friendshare':
					   $fromFriendShare=pdo_fetch("SELECT from_friend_share FROM ims_act_times Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromFriendShare['from_pyq_share'] == 0){
							//$updateBtnQdj=$btn_enter['btn_enter']+1;
							//数据库更新data
							$from_friend_share_data = array(
								    'from_friend_share' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('act_times', $from_friend_share_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 通过朋友圈推广进来的用户    更新 {$openId}",'actlogs');
							}
						}
					  break;
					default:
					  echo '非法操作';
					  exit;
					}

					$users=$this->doMobileGetUserInfoByOauth();

					//var_export($users);

					//TODO....
					//在这里拉取用户头像的时候可以拿到用户的信息 有一个subscribe参数
					//用户是否订阅该公众号标识，值为0时，代表此用户没有关注该公众号，拉取不到其余信息。
					//解决关注用户和非关注用户 玩这个游戏数据记录


					//获取拉取卡券列表的配置
//					$cardInfo=$this->doMobileGetCardSign();
					$nickname=$users['nickname'];
					$headimgurl=$users['headimgurl'];
					include $this->template('member/christmas');
				}
			}
			}

		//$sign=$this->GetCardSign();
		//var_dump($sign);
		//var_dump($sign);
//		$nickname=$_W['fans']['tag']['nickname'];
//		$headimgurl=$_W['fans']['tag']['headimgurl'];
//

	/*
		//限制仅微信可以浏览
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
		    // 非微信浏览器禁止浏览
		    echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/christmas/images/thief.gif"/>';
		    echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
		} else {
			//拿到用户头像
			load()->func('communication');

			$token = ihttp_get('http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=getToken&m=member');
			$access_token=$this->doMobileGetToken();

			$userinfo = ihttp_get("https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openId}&lang=zh_CN");

			$userinfoJson=$userinfo['content'];
			$users=json_decode($userinfoJson,true);

			//var_export($users);


			$nickname=$users['nickname'];
			$headimgurl=$users['headimgurl'];

			include $this->template('member/christmas');
		}
	 */
//			load()->func('communication');
//
//			$token = ihttp_get('http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=getToken&m=member');
//			$access_token=$this->doMobileGetToken();
//
//			$userinfo = ihttp_get("https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openId}&lang=zh_CN");
//
//			$userinfoJson=$userinfo['content'];
//			$users=json_decode($userinfoJson,true);


	/*
			$users=$this->doMobileGetUserInfoByOauth();

			//var_export($users);

			//TODO....
			//在这里拉取用户头像的时候可以拿到用户的信息 有一个subscribe参数
			//用户是否订阅该公众号标识，值为0时，代表此用户没有关注该公众号，拉取不到其余信息。
			//解决关注用户和非关注用户 玩这个游戏数据记录


			//获取拉取卡券列表的配置
			$cardInfo=$this->doMobileGetCardSign();
			$nickname=$users['nickname'];
			$headimgurl=$users['headimgurl'];
			include $this->template('member/christmas');
	*/
	}

	/**
	 * 网页授权获取用户信息
	 * dc
	 */
	public function doMobileGetUserInfoByOauth(){
//		load()->model('mc');
//		// 假设当前应用必需会员头像
//		$avatar = '';

		load()->model('mc');
		$avatar = '';
		$name = '';

		if (!empty($_W['member']['uid'])) {
			$member = mc_fetch(intval($_W['member']['uid']), array('avatar'));
			$name = mc_fetch(intval($_W['member']['uid']), array('nickname'));
			if (!empty($member)) {
				$avatar = $member['avatar'];
				$name = $member['nickname'];
			}
		}
		if (empty($avatar)) {
			$fan = ($_W['openid']);
			if (!empty($fan)) {
				$avatar = $fan['avatar'];
				$name = $fan['nickname'];
			}
		}
		if (empty($avatar)) {
			$userinfo = mc_oauth_userinfo();
			if (!is_error($userinfo) && !empty($userinfo) && is_array($userinfo) && !empty($userinfo['avatar'])) {
				$avatar = $userinfo['avatar'];
				$name = $userinfo['nickname'];
			}
		}
		if (empty($avatar) && !empty($_W['member']['uid'])) {
			$avatar = mc_require($_W['member']['uid'], array('avatar'));
			$name = mc_require($_W['member']['uid'], array('nickname'));
		}
		if (empty($avatar) || empty($name)) {
			// 提示用户关注公众号。;
			//echo "最终没有获取到头像,follow: {$_W['fans']['follow']}";

		} else {
			$user=array(
				'headimgurl'=>$avatar,
				'nickname'=>$name
			);
			return $user;
		}
	}

	/**
	 * 小火车抽奖 获取addcard配置
	 * daichen
	 */
	public function doMobileGetCardSignInfo(){
		global $_W;
		$timestamp=$_W['timestamp'];
		$cticket=$this->doMobileGetCardS();
		$card_id=htmlspecialchars($_POST['cardId']);
		//var_dump($cticket);
		//$card_id='pUdGzjpg82vkm-ZW31q0I2M_LE68';

		$nonce_str=$this->generateNonceStr();
        $card = array(
                    $timestamp,
                    $cticket,
                    $card_id,
                    $nonce_str
                );
        sort($card,SORT_STRING);
        foreach($card as $k=>$v){
            $return .= $v;
        }
//		var_dump($cticket);
//		var_dump($card);

		//$sign=$_W['account']['signature'];
		$sign=sha1($return);
		$res=array(
			'timestamp'=>$timestamp,
			'signature'=>$sign,
			'noncestr'=>$nonce_str,
		);
//		var_dump($res);
		echo json_encode($res);
    }


	// 外部链接拉起微信会员卡
	public function doMobileGetCustomerCardSignInfo(){
		global $_W;
		$timestamp=$_W['timestamp'];
		$cticket=$this->doMobileGetCardS();
		$card_id=$this->doMobileGetWxcardid();
		//var_dump($cticket);
		//$card_id='pUdGzjpg82vkm-ZW31q0I2M_LE68';

		$nonce_str=$this->generateNonceStr();
        $card = array(
                    $timestamp,
                    $cticket,
                    $card_id,
                    $nonce_str
                );
        sort($card,SORT_STRING);
        foreach($card as $k=>$v){
            $return .= $v;
        }
//		var_dump($cticket);
//		var_dump($card);

		//$sign=$_W['account']['signature'];
		$sign=sha1($return);
		$res=array(
			'timestamp'=>$timestamp,
			'signature'=>$sign,
			'noncestr'=>$nonce_str,
		);
//		var_dump($res);
		echo json_encode($res);
    }

	/**
	 * 小火车抽奖 获取随机字符串 dc
	 * daichen
	 */
	public function generateNonceStr($length=16){
	    // 密码字符集，可任意添加你需要的字符
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	    $str = "";
	    for($i = 0; $i < $length; $i++)
	    {
	    $str .= $chars[mt_rand(0, strlen($chars) - 1)];
	    }
	    return $str;
	}

	/**
	 * 小火车抽奖 获得用户已经拿到的会员卡
	 * daichen
	 */
	public function doMobileGetReceivedCardList(){

		$openId=$_POST['openId'];

		$access_token=$this->doMobileGetToken();
		$cardId = "";
		$sendapi = "https://api.weixin.qq.com/card/user/getcardlist?access_token={$access_token}";
		$data = array(
		    'openid' => $openId,
		    'card_id' => ''
		);
		$datas=json_encode($data);
		load()->func('communication');
		$response = ihttp_post($sendapi, $datas);
		$cardlistContent=$response['content'];


		$arr=json_decode($cardlistContent,true);
		$res=$arr['card_list'];

		foreach($res as $key=>$value){

			$arrs[$key]['cardId']=$value['card_id'];
			$arrs[$key]['code']=$value['code'];

		}

//		$nullarr=array(
//
//			array(
//				'cardId'=>"",
//				'code'=>""
//			)
//
//		);

	//	if($arrs!=''){
			echo json_encode($arrs);
//		}else{
//			echo json_encode($nullarr);
//		}
	}

	/**
	 * 小火车抽奖 获得 $api_ticket
	 * daichen
	 */
	public function doMobileGetCardS(){
		global $_W;
//
		//取缓存
		//cache_delete('dcdyr_api_ticket');
		$dcdyr_ticket=cache_load('dcdyr_api_ticket');

		//var_dump($dcdyr_ticket);
		//var_dump(time());
		//如果缓存的时间 小鱼当前时间 那么 重新获取并缓存
		if($dcdyr_ticket['exp']<time()){
			//echo "没走缓存";
			load()->func('communication');
			$access_token=$this->doMobileGetToken();
			$userinfo = ihttp_get("https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type=wx_card");
			$ticketJson=$userinfo['content'];
			$ticketArr=json_decode($ticketJson,true);
			$ticket=$ticketArr['ticket'];
			//缓存时间为当前时间加7000秒  实际为7200秒
			$cacheTime=time()+6000;
			$cacheTicket=array(
				'ticket'=>$ticket,
				'exp'=>$cacheTime,
			);
			cache_write('dcdyr_api_ticket', $cacheTicket);

			return $cacheTicket['ticket'];
		}
//			cache_delete('api_ticket');
			//echo "走了缓存";
			//var_dump($dcdyr_ticket['ticket']);
			return $dcdyr_ticket['ticket'];
	}


	/**
	 * 小火车抽奖 中奖算法
	 * 孙晓远
	 *
		输入:第几次抽   $times
			 中奖次数   $tickets_times
			 商品余量   $store_num
			 产品总量   $store_amount
	 */
	public function doMobileMathMethod($times,$tickets_times,$store_num,$store_amount){

		$rand=20;

		$fre = $tickets_times*(-$rand-$times*($rand-1))+$times*$rand+($store_num/$store_amount)*$rand;

		$temp = mt_rand('0','100');

		if ($temp <=$fre)
		{
			if($temp>=5&&$store_num>1){
			return 1;
			}
			else {
				return 2;
				}
		}
		else{
			return 0;
			}
	}

	/**
	 * 小火车控制器
	 */
	public function doMobileSysControl(){



		if(isset($_POST['sagaControl']) && $_POST['sagaControl']!='' ){

			if($_POST['sagaControl'] == 'offdaichen'){
					$data=array(
						'sys_on_off'=>0,
					);
					pdo_update('act_trainsys', $data);
			}elseif($_POST['sagaControl'] == 'ondaichen'){
					$data=array(
						'sys_on_off'=>1,
					);
					pdo_update('act_trainsys', $data);
			}
		}else{
			echo "非法操作";
		}


		include $this->template('member/christmassys');

	}

	/**
	 * 小火车抽奖 领卡资格判断接口
	 * daichen
	 *
	 *
	 */
	//当用户第一次进来时 插入用户信息
	//重置隔天用户信息，如果隔天或隔更多天 重置次数
	//判断是否还有剩余领卡次数
	//算法判断其是否中奖
	//如果中奖进一层判断是否还有券，如果没有券了那么给冰棍，如果有那么给这个ID
	//返回中奖信息

	public function doMobileWhetherGetCard(){



		$openId=$_POST['openId'];
		$cardId=htmlspecialchars($_POST['cardId']);
		$cardName=htmlspecialchars($_POST['cardName']);

//		$openId=123;
//		$cardId=66666666;
//		$cardName='大香肠';



//		$users=pdo_fetch("SELECT openId FROM ims_act_times Where openId='".$openId."'");
//
//		//原始抽奖次数配置
//		$orginTimes=3;
//		//第一次用户进来 插入用户数据
//		if(empty($users)){
//			$userInfo=array('openId'=>$openId,'times'=>$orginTimes);
//	    	$lookresult = pdo_insert('act_times', $userInfo);
//		}

//		//拿到用户最后一次抽奖的时间
//		$lastTime=pdo_fetch("SELECT lastTime FROM ims_act_times Where openId='".$openId."'");
//
//		$time=substr($lastTime['lastTime'],0,strrpos($lastTime['lastTime'],' '));
//
//		//拿到用户最后一次抽奖时间 格式为20161209
//		$timeRes=str_replace('-', '',$time);
//		//拿到当前时间20161210
//		$nowTime=date('Ymd');
//
//		//如果是隔天或者隔更多天，则重置剩余次数为3
//		if($nowTime-$timeRes>=1){
//			$reset_data=array(
//					'times'=>3
//				);
//			pdo_update('act_times', $reset_data, array('openId' => $openId));
//		}

		$times=pdo_fetch("SELECT times FROM ims_act_times Where openId='".$openId."'");

		//判断是否还有剩余领卡次数
		if($times['times']<=0){
			$errmsgArr=array('status'=>0,'station'=>0,'message'=>'您的领卡次数不足啦！试试分享吧!~');
			$jerrmsg=json_encode($errmsgArr);
			echo $jerrmsg;
		}else{
			//TODO..算法判断是否中奖

				//是否中过奖
				//$is_rec=pdo_fetch("SELECT is_receive FROM ims_act_times Where openId='".$openId."'");
				//

				//第几次抽
				$times=$this->doMobileGetTimes($openId);

//				echo "第'".$times."'次 抽奖 <br />";

				//是否中过奖 如果中过返回1 没中过返回0
				$is_rec=pdo_fetch("SELECT is_receive FROM ims_act_times Where openId='".$openId."'");

				//如果中过 那么中奖次数为1 如果没中过 那么中奖次数为0
				if($is_rec['is_receive']==0){
					$tickets_times=0;
				}else{
					$tickets_times=1;
				}

				//获得商品总量和商品余量
				$store=pdo_fetch("SELECT amount_num,s_num FROM ims_act_cardlist Where cardId='".$cardId."'");

				$store_amount=$store['amount_num'];
				$store_num=$store['s_num'];

				//是否中奖 0 未中奖 1中奖 2冰棍
				$WhetherLuck=$this->doMobileMathMethod($times,$tickets_times,$store_num,$store_amount);

//				echo "中奖状态为'".$WhetherLuck."' <br /> ";

			/*
				$luckList=array('oUdGzjpr6FrpCfU_pQbYEJTGE6qg');

				if(in_array($openId, $luckList)){
					$WhetherLuck=1;
				};
			*/
				//查询保底卡券
				$card_Free=pdo_fetchall("SELECT cardId,cardName,pro_name FROM ims_act_cardlist Where on_off_baodi='1'");

				//var_dump($card_Free);


				if($WhetherLuck==1){
					//中奖了 卡券是否有  没有返回false 有返回true
					$flagRes=$this->doMobileIsExsitCarId($WhetherLuck,$cardId);

					//如果中奖了 而且有库存 返回这个cardId
					if($flagRes){

//						echo "我中奖了";

						//如果中奖 将此人的中奖状态改为1 记录此人已经中过奖了 要降低中奖率

						$data=array(
							'is_receive'=>1,
						);

						pdo_update('act_times', $data, array('openId' => $openId));

//						echo "当前库存为'".$store_num."' <br /> ";

						//如果中奖 库存减少
						$s_num_update=$store_num-1;
						$s_data=array(
							's_num'=>$s_num_update,
						);

						pdo_update('act_cardlist', $s_data, array('cardId' => $cardId));

						//查询中奖卡券图片路径
						$card_img=pdo_fetch("SELECT card_img FROM ims_act_cardlist Where cardId='".$cardId."'");
						$msgArr=array(
							'status'=>1,
							'station'=>1,
							'cardId_free'=>$cardId,
							'card_img'=>$card_img,
						);
						$jsonMsg=json_encode($msgArr);
						echo $jsonMsg;
					}else{

//						echo "因为没有了库存 所以 我中了冰棍";

						//如果中奖 将此人的中奖状态改为1 记录此人已经中过奖了 要降低中奖率
						$data=array(
							'is_receive'=>1,
						);

						pdo_update('act_times', $data, array('openId' => $openId));

						//随机抽取保底卡券概率
						//$freeCarIDProbability=(10/count($card_Free));


						//随机获得保底卡券信息
						//获得 0 到 保底卡券数量的随机整数
						$ranDom=rand(0, count($card_Free)-1);

						$cardId_bd = $card_Free[$ranDom]['cardId'];
						$cardname_bd = $card_Free[$ranDom]['cardName'];
						$proname_bd=$card_Free[$ranDom]['pro_name'];

						/*
						if(rand(0, 10)>$freeCarIDProbability){
							$cardId_bd=$card_Free[0];
							$cardname_bd='loveice factory   冰工厂';
						}else{
							$cardId_bd=$card_Free[1];
							$cardname_bd='王妃家的火锅';
						}
						*/


						$msgfalseArr=array(
							'status'=>2,
							'station'=>1,
							'cardId_free'=>$cardId_bd,  //保底CARDID
							'cardFree_name'=>$cardname_bd,	//保底CARDNAME
							'proFree_name'=>$proname_bd,
							'card_img'=>'http://img5.imgtn.bdimg.com/it/u=2124514607,424831572&fm=21&gp=0.jpg',
						);

						$jsonfalseMsg=json_encode($msgfalseArr);
						echo $jsonfalseMsg;
					}

				}elseif($WhetherLuck==2){

//						echo "我中了冰棍";

						//如果中奖 将此人的中奖状态改为1 记录此人已经中过奖了 要降低中奖率
						$data=array(
							'is_receive'=>1,
						);

						pdo_update('act_times', $data, array('openId' => $openId));

						/*
						//随机抽取保底卡券概率
						$freeCarIDProbability=(10/count($card_Free));

						if(rand(0, 10)>$freeCarIDProbability){
							$cardId_bd=$card_Free[0];
							$cardname_bd='loveice factory   冰工厂';
						}else{
							$cardId_bd=$card_Free[1];
							$cardname_bd='王妃家的火锅';
						}
						*/

						//随机获得保底卡券信息
						//获得 0 到 保底卡券数量的随机整数
						$ranDom=rand(0, count($card_Free)-1);

						$cardId_bd = $card_Free[$ranDom]['cardId'];
						$cardname_bd = $card_Free[$ranDom]['cardName'];
						$proname_bd=$card_Free[$ranDom]['pro_name'];

						$msgfalseArr=array(
							'status'=>2,
							'station'=>1,
							'cardId_free'=>$cardId_bd,  //保底CARDID
							'cardFree_name'=>$cardname_bd,	//保底CARDNAME
							'proFree_name'=>$proname_bd,
							'card_img'=>'http://img5.imgtn.bdimg.com/it/u=2124514607,424831572&fm=21&gp=0.jpg',
						);
						$jsonfalseMsg=json_encode($msgfalseArr);
						echo $jsonfalseMsg;

				}else{

//						echo "我没中奖";
						//如果没中奖 返回0状态 没有cardId
						$msgNoLuckArr=array(
								'status'=>0,
								'station'=>1,
								'cardId_free'=>''
							);
						$jsonNoLuckMsg=json_encode($msgNoLuckArr);
						echo $jsonNoLuckMsg;
				}



				//开启事物操作 look日志和次数减少 要同时完成
    			pdo_begin();

				$lookdata=array('openId'=>$openId,'cardId'=>$cardId,'cardName'=>$cardName,'WhetherLuck'=>$WhetherLuck);
				//查看一次 写入查看日志
    			$lookresult = pdo_insert('act_lookcardlog', $lookdata);

//				$openId=$_POST['openId'];
				//查此人剩余的次数
				$times=pdo_fetch("SELECT times FROM ims_act_times Where openId='".$openId."'");

				$updateTimes=$times['times']-1;

				//申请成功一次 次数-1
				$times_data = array(
				    'times' => $updateTimes,
				);
				//更新抽奖次数信息
				$result = pdo_update('act_times', $times_data, array('openId' => $openId));

				//如果查看日志和剩余次数更新同时执行成功 那么提交 否则回滚
    			if ($result == true &&  $lookresult == true) {


    				pdo_commit();
					//提交事务 返回成功操作JSON数据
//					$msgarr=array('status'=>$status,'cardId_free'=>$cardId);
//					$msgarrJson=json_encode($msgarr);
//					echo $msgarrJson;
    			}else{


    				logs("没有成功记录此次抽奖行为 事务回滚 {$openId}",'actlogs');
    				//echo "没有成功记录此次抽奖行为 <br />";
    				pdo_rollback();
					//回滚事务 返回失败操作JOSN数据
//					$errmsgarr=array('status'=>0,'cardId_free'=>'');


//
    			}
			}

	}

	/*
	 * 小火车抽奖 记录领卡动作接口
	 * daichen
	 *
	 * {
		"status": 0,
		"message": "系统错误，请重新领取"
		}
	 * {
		"status": 1,
		"message": "恭喜你,领卡成功!"
		}
	 */
	public function doMobileGetCardLog(){

		$openId=$_POST['openId'];
		$cardId=htmlspecialchars($_POST['cardId']);
		$cardName=htmlspecialchars($_POST['cardName']);
		//添加记录，并判断是否成功
		$getcard_data = array(
		    'openId' => $openId,
		    'cardId' => $cardId,
		    'cardName'=>$cardName,
//		    'time'=>time()
		);

		if($cardId!=''){


			$result = pdo_insert('act_getcardlog', $getcard_data);

				if (!empty($result)) {
				   $msgarr=array('status'=>1,'message'=>'恭喜你,领卡成功!');
				   $msgJson=json_encode($msgarr);
				   echo $msgJson;
				}else{
					$errmsg=array('status'=>0,'message'=>'系统错误，请重新领取');
					$errmsgJson=json_encode($errmsg);
					echo $errmsgJson;
				}

		}

	}


	/*
	 * 小火车抽奖 今日剩余领卡次数接口
	 * daichen
	 * {
		"status": "1",
		"times": 1000
	   }
	 * {
		"status": "0",
		"times": ''
	   }
	 *
	 *
	 *
	 */
	public function doMobileGetTodayTimes(){

		$openId=$_POST['openId'];
		//$users=pdo_fetch("SELECT openId FROM ims_act_times Where openId='".$openId."'");
		//空数据 无法插入

		/*
		if($openId!='' && isset($openId)){
			//原始抽奖次数配置
			$orginTimes=3;
			//第一次用户进来 插入用户数据
			if(empty($users)){

				$userInfo=array('openId'=>$openId,'times'=>$orginTimes);
		    	$lookresult = pdo_insert('act_times', $userInfo);
			}

		}
		*/
/*
	    //因为业务流程原因 将重置更改到入场就重置
		//拿到用户最后一次抽奖的时间
		$lastTime=pdo_fetch("SELECT lastTime,attend_times FROM ims_act_times Where openId='".$openId."'");

		$time=substr($lastTime['lastTime'],0,strrpos($lastTime['lastTime'],' '));

		//拿到用户隔天参与的次数 累计
		$attend_times=$lastTime['attend_times'];

		//拿到用户最后一次抽奖时间 格式为20161209
		$timeRes=str_replace('-', '',$time);
		//拿到当前时间20161210
		$nowTime=date('Ymd');

		//如果是隔天或者隔更多天，则重置剩余次数为3 变为可以转发
		if($nowTime-$timeRes>=1){
			$reset_data=array(
					'times'=>3,
					'status'=>1,
					'attend_times'=>$attend_times+1
				);
			pdo_update('act_times', $reset_data, array('openId' => $openId));
		}

*/


			//查此人剩余的次数
		$times=pdo_fetch("SELECT times,status FROM ims_act_times WHERE openId='".$openId."'");
		if(isset($times) && $times!=''){
			$data=array('status'=>'1','times'=>$times['times'],'is_share'=>$times['status']);
			$jsonData=json_encode($data);
			echo $jsonData;
		}else{
			$errdata=array('status'=>'0','times'=>'','is_share'=>$times['status']);
			$errjsonData=json_encode($data);
			echo $errjsonData;
		}



	}


	/*
	 * 小火车抽奖 更改打劫次数接口
	 * daichen
	 * {
		"status": "1",
		"message": "分享成功"
	   }
	 * {
		"msg": "0",
		"message": "抱歉，今日您已经分享过了，明日再来！"
	    }
	 *
	 */
	public function doMobileEditGetTimes(){

		$openId=$_POST['openId'];
			//查此人剩余的次数

		$status=pdo_fetch("SELECT status FROM ims_act_times Where openId='".$openId."'");

		//判断今日是否分享过 若没有分享过进行分享增加次数逻辑
		if($status['status']==1){
			$times=pdo_fetch("SELECT times FROM ims_act_times Where openId='".$openId."'");

			$updateTimes=$times['times']+1;

			//分享成功 给此人剩余的次数+1
			$times_data = array(
			    'times' => $updateTimes,
			);

			//更新次数信息
			$result = pdo_update('act_times', $times_data, array('openId' => $openId));

			if (!empty($result)) {

				$status_data=array(
					'status'=>0
				);

				//分享成功 status置为0 表示
				$statusRes=pdo_update('act_times', $status_data, array('openId' => $openId));

				if(!empty($statusRes)){
					$message=array('status'=>'1','message'=>'分享成功');
					$jsonMessage=json_encode($message);
					echo $jsonMessage;
				}

			}
		}else{
			$msg=array('status'=>'0','message'=>'抱歉，今日您已经分享过了，明日再来！');
			$jsonMsg=json_encode($msg);
			echo $jsonMsg;
		}
	}

	/*
	 * 小火车抽奖 卡券列表接口
	 * daichen
	 * 返回JSON格式
	 * [
			{
			"cardId": "66666666",
			"cardName": "大香肠",
			"s_num": "100"
			},
			{
			"cardId": "11111111",
			"cardName": "香豆腐",
			"s_num": "100"
			}
		]
	 */
	public function doMobileGetCardList(){

			//$card_Free=pdo_fetchall("SELECT cardId,cardName FROM ims_act_cardlist Where on_off_baodi='1'");
			//var_dump($card_Free);
			$cardlist = pdo_fetchall("SELECT cardId,cardName,s_num,train_img,card_img,act_price,pro_name FROM ims_act_cardlist Where on_off='1'  ORDER BY listorder");

//			for($i=0;$i<count($cardlist);$i++){
//				if($cardlist[i]['s_num']<=0){
//
//				}else{
//					$cardlistRes[]=$cardlist[i];
//				}
//
//			}
//
//			var_dump($cardlistRes);
//
			$cardlistJson=json_encode($cardlist);
			echo $cardlistJson;

	}

	/**
	 * 小火车抽奖 获得用户是第几次抽奖的
	 * daichen
	 */
	public function doMobileGetTimes($openId){

		//查出用户剩余次数和是否转发过

		$res=pdo_fetch("SELECT times,status FROM ims_act_times Where openId='".$openId."'");

		$status=$res['status'];
		$times=$res['times'];

		//如果用户没有转发过
		if($status==1 && $times!=0){

			//返回接下来是第几次抽奖的
			$sTimes=3-$times+1;
			return $sTimes;
		}else{
			//如果用户转发过 返回接下来是第几次抽奖的
			if($times==4){
				return 1;
			}elseif($times==3){
				return 2;
			}elseif($times==2){
				return 3;
			}elseif($times==1){
				return 4;
			}

		}

	}

	/**
	 * 小火车抽奖 卡券是否有余量
	 * daichen
	 */
	public function doMobileIsExsitCarId($is_luck,$cardId){
		if($is_luck==1){
			$s_num=pdo_fetch("SELECT s_num FROM ims_act_cardlist Where cardId='".$cardId."'");
			if($s_num['s_num']<=0){
				return false; //如果库存不够 给冰棍
			}else{
				return true; //如果库存够 返回1
			}
		}
	}


	 #***********************#
	 #	  小火车用户行为统计部分         #
	 #***********************#

	/**
	 * 用户是否点击过“去打劫” 按钮
	 * dc
	 */
	public function doMobileIsClickedQdj(){
		//获取OPENID
		$openId=$_POST['openId'];
		//查询此用户信息
		$btn_qdj=pdo_fetch("SELECT btn_qdj FROM ims_act_times Where openId='".$openId."'");
		//判断用户是否有此行为
		//次数+1
		if($btn_qdj['btn_qdj'] == 0){
			//$updateBtnQdj=$btn_qdj['btn_qdj']+1;
			//数据库更新data 变1
			$btn_qdj_data = array(
				    'btn_qdj' => 1,
			);
			//更新通过朋友分享次数信息
			$result = pdo_update('act_times', $btn_qdj_data, array('openId' => $openId));
			//更新成功
			if (!empty($result)) {
				$message=array('status'=>'1','message'=>'更新成功');
				$jsonMessage=json_encode($message);
				echo $jsonMessage;
			}else{
				//没有成功 记录日志
				logs("没有成功记录此次通过 用户是否点击过“去打劫” 按钮   更新 {$openId}",'actlogs');
			}
		}

	}


	/**
	 * 用户是否点击过“进入” 按钮
	 * dc
	 */
	public function doMobileIsClickedEnter(){
		//获取OPENID
		$openId=$_POST['openId'];
		//查询此用户信息
		$btn_enter=pdo_fetch("SELECT btn_enter FROM ims_act_times Where openId='".$openId."'");
		//判断用户是否有此行为
		//次数+1
		if($btn_enter['btn_enter'] == 0){
			//$updateBtnQdj=$btn_enter['btn_enter']+1;
			//数据库更新data
			$btn_enter_data = array(
				    'btn_enter' => 1,
			);
			//更新通过朋友分享次数信息
			$result = pdo_update('act_times', $btn_enter_data, array('openId' => $openId));
			//更新成功
			if (!empty($result)) {
				$message=array('status'=>'1','message'=>'更新成功');
				$jsonMessage=json_encode($message);
				echo $jsonMessage;
			}else{
				//没有成功 记录日志
				logs("没有成功记录此次通过 用户是否点击过“进入” 按钮   更新 {$openId}",'actlogs');
			}
		}

	}


	/**
	 * 用户是否点击过“背包” 按钮
	 * dc
	 */
	public function doMobileIsClickedPackage(){
		//获取OPENID
		$openId=$_POST['openId'];
		//查询此用户信息
		$btn_package=pdo_fetch("SELECT btn_package FROM ims_act_times Where openId='".$openId."'");
		//判断用户是否有此行为
		//次数+1
		if($btn_package['btn_package'] == 0){
			//$updateBtnQdj=$btn_enter['btn_enter']+1;
			//数据库更新data
			$btn_package_data = array(
				    'btn_package' => 1,
			);
			//更新通过朋友分享次数信息
			$result = pdo_update('act_times', $btn_package_data, array('openId' => $openId));
			//更新成功
			if (!empty($result)) {
				$message=array('status'=>'1','message'=>'更新成功');
				$jsonMessage=json_encode($message);
				echo $jsonMessage;
			}else{
				//没有成功 记录日志
				logs("没有成功记录此次通过 用户是否点击过“背包” 按钮   更新 {$openId}",'actlogs');
			}
		}

	}

	public function doMobileDataDetil(){
		include $this->template('member/christmasdata');
	}
	public function doMobileDataGetDetil(){
		include $this->template('member/christmasdataget');
	}

	
	public function doMobileDataGetDetilAll(){
		
		include $this->template('member/christmasgetcardall');
	}
	
	
	/**
	 * 小火车通过分享给朋友分享记录
	 * dc
	 */
	public function doMobileShareByFriend(){
		//获取OPENID
		$openId=$_POST['openId'];

		//$openId='oUdGzjpr6FrpCfU_pQbYEJTGE6qg';
		//查询此用户通过发送给朋友几次
		$shareby_friend=pdo_fetch("SELECT shareby_friend FROM ims_act_times Where openId='".$openId."'");
		//次数+1
		//$updateSharebyFriend=$shareby_friend['shareby_friend']+1;
		//数据库更新data

		if($shareby_friend['shareby_friend'] == 0){

			$shareby_friend_data = array(
				    'shareby_friend' => 1,
			);
			//更新通过朋友分享次数信息
			$result = pdo_update('act_times', $shareby_friend_data, array('openId' => $openId));

			//更新成功
			if (!empty($result)) {
				$message=array('status'=>'1','message'=>'更新成功');
				$jsonMessage=json_encode($message);
				echo $jsonMessage;
			}else{
				//没有成功 记录日志
				logs("没有成功记录此次通过朋友分享次数更新 {$openId}",'actlogs');
			}
		}
	}

	/**
	 * 小火车通过分享到朋友圈分享记录
	 * dc
	 */
	public function doMobileShareByPyq(){
		//获取OPENID
		$openId=$_POST['openId'];
		//查询此用户通过发送给朋友几次
		$shareby_pyq=pdo_fetch("SELECT shareby_pyq FROM ims_act_times Where openId='".$openId."'");
		//次数+1
		//$updateSharebypyq=$shareby_pyq['shareby_pyq']+1;

		if($shareby_pyq['shareby_pyq'] == 0){
		//数据库更新data
			$shareby_friend_data = array(
				    'shareby_pyq' => 1,
			);
			//更新通过朋友分享次数信息
			$result = pdo_update('act_times', $shareby_friend_data, array('openId' => $openId));

			//更新成功
			if (!empty($result)) {
				$message=array('status'=>'1','message'=>'更新成功');
				$jsonMessage=json_encode($message);
				echo $jsonMessage;
			}else{
				//没有成功 记录日志
				logs("没有成功记录此次通过朋友圈分享次数更新 {$openId}",'actlogs');
			}
		}

	}

	/**
	 * 小火车CHART统计绘制 打劫次数
	 * dc
	 */
	public function doMobileTrainChart(){
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:POST,GET');
		$cardId=htmlspecialchars($_POST['cardId']);

		$nowTime=time();

		$Time_day=date('Y-m-d', $nowTime);
		$begin_Time=$Time_day.' 00:00:00';
		$unixBeginTime=strtotime($begin_Time);
		//echo $end_Time=$unixBeginTime+3600*24;
		$dateArr=array();


		for($i=0;$i<=24;$i++){
			//24小时
			//开始时间:00:00:00时间戳 +3600*0
			//结束时间:00:00:00时间戳 +3600+1

			$selBeginTime = $unixBeginTime+3600*$i;
			$selEndTime = $unixBeginTime+3600*($i+1);
			$cardId=$cardId;
			$sql="SELECT count(*) FROM ims_act_lookcardlog Where cardId='".$cardId."'
			and UNIX_TIMESTAMP(Time)>='".$selBeginTime."'
			and UNIX_TIMESTAMP(Time)<='".$selEndTime."'";



			$cardAttendTimes=pdo_fetch($sql);
			$dateArr[$i]=$cardAttendTimes['count(*)'];

		}
		echo json_encode($dateArr);
		//var_dump($dateArr);

	}


		/**
	 * 小火车CHART统计绘制 领取状态
	 * dc
	 */
	public function doMobileTrainChartGetCard(){
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:POST,GET');
		$cardId=htmlspecialchars($_POST['cardId']);

		$nowTime=time();

		$Time_day=date('Y-m-d', $nowTime);
		$begin_Time=$Time_day.' 00:00:00';
		$unixBeginTime=strtotime($begin_Time);
		//echo $end_Time=$unixBeginTime+3600*24;
		$dateArr=array();


		for($i=0;$i<=24;$i++){
			//24小时
			//开始时间:00:00:00时间戳 +3600*0
			//结束时间:00:00:00时间戳 +3600+1

			$selBeginTime = $unixBeginTime+3600*$i;
			$selEndTime = $unixBeginTime+3600*($i+1);
			$cardId=$cardId;
			$sql="SELECT count(*) FROM ims_act_getcardlog Where cardId='".$cardId."'
			and UNIX_TIMESTAMP(Time)>='".$selBeginTime."'
			and UNIX_TIMESTAMP(Time)<='".$selEndTime."'";



			$cardAttendTimes=pdo_fetch($sql);
			$dateArr[$i]=$cardAttendTimes['count(*)'];

		}
		echo json_encode($dateArr);
		//var_dump($dateArr);

	}


	/**
	 * 小火车CHART统计绘制 打劫全部商户
	 * dc
	 */
	public function doMobileTrainChartAll(){
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:POST,GET');
		
		$cardlist = pdo_fetchall("SELECT cardId,cardName,s_num,train_img,card_img,act_price,pro_name FROM ims_act_cardlist Where on_off='1'  ORDER BY listorder");

		$nowTime=time();
		
		
		$Time_day=date('Y-m-d', $nowTime);
		
		if(isset($_POST['sel_time']) && $_POST['sel_time']!='' ){
			$Time_day=$_POST['sel_time'];
		}
		
		//var_dump($Time_day);
		$begin_Time=$Time_day.' 00:00:00';
		$unixBeginTime=strtotime($begin_Time);		
		//echo $end_Time=$unixBeginTime+3600*24;
		$dateArr=array();
		
		
		foreach($cardlist as $key=>$value){
			for($i=0;$i<=24;$i++){
				//24小时
				//开始时间:00:00:00时间戳 +3600*0
				//结束时间:00:00:00时间戳 +3600+1
				
				$selBeginTime = $unixBeginTime+3600*$i;
				$selEndTime = $unixBeginTime+3600*($i+1);
				$cardId=$cardId;
				$sql="SELECT count(*) as times,cardName FROM ims_act_lookcardlog Where cardId='".$value['cardId']."' 
				and UNIX_TIMESTAMP(Time)>='".$selBeginTime."'
				and UNIX_TIMESTAMP(Time)<='".$selEndTime."'"; 
				
				$cardAttendTimes=pdo_fetch($sql);
//				$dateArr[$key][$i]=$cardAttendTimes;
				$dateArrTimes[$key][0][]=$cardAttendTimes['times'];
				if($cardAttendTimes['cardName']!=NULL){
					$dateArrTimes[$key][1][0]=$cardAttendTimes['cardName'];
				}
				

			}
			//$dateArr[$key][$key][]
		}
		
		
		//var_dump($dateArrTimes);
		//var_dump($dateArr);
		echo json_encode($dateArrTimes);
		//var_dump($dateArr);

	}


	 /**
	 * 小火车CHART统计绘制 打劫全部商户 领卡状况
	 * dc
	 */
	public function doMobileTrainChartGetAll(){
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:POST,GET');
		
		$cardlist = pdo_fetchall("SELECT cardId,cardName,s_num,train_img,card_img,act_price,pro_name FROM ims_act_cardlist Where on_off='1'  ORDER BY listorder");

		$nowTime=time();
		
		
		$Time_day=date('Y-m-d', $nowTime);
		
		if(isset($_POST['sel_time']) && $_POST['sel_time']!='' ){
			$Time_day=$_POST['sel_time'];
		}
		
		//var_dump($Time_day);
		$begin_Time=$Time_day.' 00:00:00';
		$unixBeginTime=strtotime($begin_Time);		
		//echo $end_Time=$unixBeginTime+3600*24;
		$dateArr=array();
		
		
		foreach($cardlist as $key=>$value){
			for($i=0;$i<=24;$i++){
				//24小时
				//开始时间:00:00:00时间戳 +3600*0
				//结束时间:00:00:00时间戳 +3600+1
				
				$selBeginTime = $unixBeginTime+3600*$i;
				$selEndTime = $unixBeginTime+3600*($i+1);
				$cardId=$cardId;
				$sql="SELECT count(*) as times,cardName FROM ims_act_getcardlog Where cardId='".$value['cardId']."' 
				and UNIX_TIMESTAMP(Time)>='".$selBeginTime."'
				and UNIX_TIMESTAMP(Time)<='".$selEndTime."'"; 
				
				$cardAttendTimes=pdo_fetch($sql);
//				$dateArr[$key][$i]=$cardAttendTimes;
				$dateArrTimes[$key][0][]=$cardAttendTimes['times'];
				if($cardAttendTimes['cardName']!=NULL){
					$dateArrTimes[$key][1][0]=$cardAttendTimes['cardName'];
				}
				

			}
			//$dateArr[$key][$key][]
		}
		
		
		//var_dump($dateArrTimes);
		//var_dump($dateArr);
		echo json_encode($dateArrTimes);
		//var_dump($dateArr);

	}


	 /**
	 * 小火车CHART统计绘制 抽奖状态全部商户
	 * dc
	 */
	public function doMobileTrainChartGetCardAll(){
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:POST,GET');

		$cardlist = pdo_fetchall("SELECT cardId,cardName,s_num,train_img,card_img,act_price,pro_name FROM ims_act_cardlist Where on_off='1'  ORDER BY listorder");

		$nowTime=time();

		$Time_day=date('Y-m-d', $nowTime);
		$begin_Time=$Time_day.' 00:00:00';
		$unixBeginTime=strtotime($begin_Time);		
		//echo $end_Time=$unixBeginTime+3600*24;
		$dateArr=array();
		
		
		foreach($cardlist as $key=>$value){
			for($i=0;$i<=24;$i++){
				//24小时
				//开始时间:00:00:00时间戳 +3600*0
				//结束时间:00:00:00时间戳 +3600+1
				
				$selBeginTime = $unixBeginTime+3600*$i;
				$selEndTime = $unixBeginTime+3600*($i+1);
				$cardId=$cardId;
				$sql="SELECT count(*) FROM ims_act_getcardlog Where cardId='".$value['cardId']."' 
				and UNIX_TIMESTAMP(Time)>='".$selBeginTime."'
				and UNIX_TIMESTAMP(Time)<='".$selEndTime."'";
				
				$cardAttendTimes=pdo_fetch($sql);
				$dateArr[$key][$i]=$cardAttendTimes['count(*)'];
			}
		}
		echo json_encode($dateArr);
		//var_dump($dateArr);

	}
	
	

	

	// +----------------------------------------------------------------------
	// | 小火车 END daichen
	// +----------------------------------------------------------------------

	// +----------------------------------------------------------------------
	// | 猜灯谜 BEGIN daichen
	// +----------------------------------------------------------------------
	public function doMobileLightEntryTest(){
		//拿到openId
		//首次进入插入该用户信息到数据表
		//拿到该用户昵称&头像
		//渲染模板
		global $_W;
		$openId=$_W['openid'];

		if($openId!='' && isset($openId)){
				var_dump($openId);
				$user=pdo_fetch("SELECT openId FROM ims_light_users Where openId='".$openId."'");
				//$user=pdo_fetch("SELECT openId FROM ims_light_users Where openId='".$openId."'");
				//第一次用户进来 插入用户数据
				if(empty($user)){
					$userInfo=array('openId'=>$openId);
			    	$lookresult = pdo_insert('light_users', $userInfo);
				}
	    }
		$users=$this->doMobileGetUserInfoByOauth();
		$nickname=$users['nickname'];
		$headimgurl=$users['headimgurl'];
		include $this->template('member/yuanxiaoTest');
	}
	
	
	

	
	/**
	 * 灯谜控制器
	 */
	public function doMobileLightSysControl(){



		if(isset($_POST['sagaControl']) && $_POST['sagaControl']!='' ){

			if($_POST['sagaControl'] == 'offdaichen'){
					$data=array(
						'sys_on_off'=>0,
					);
					pdo_update('light_sys', $data);
			}elseif($_POST['sagaControl'] == 'ondaichen'){
					$data=array(
						'sys_on_off'=>1,
					);
					pdo_update('light_sys', $data);
			}
		}else{
			echo "非法操作";
		}


		include $this->template('member/lightsys');

	}
	
	
	/**
	 * 灯谜活动
	 * 入口
	 * daichen
	 */
	public function doMobileLightEntry(){
		//拿到openId
		//首次进入插入该用户信息到数据表
		//拿到该用户昵称&头像
		//渲染模板
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
		    // 非微信浏览器禁止浏览
		  
		    echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
		} else {
				$sys=pdo_fetch("SELECT sys_on_off FROM ims_light_sys");

				if($sys['sys_on_off'] == 0){
					include $this->template('member/yuanxiao_Fuse');
//					echo "<h4 style='display:inline-block'> :-) 对不起，当前参与人数太多，请稍后再试 ^_^ </h4>";
						//include $this->template('member/	');
						exit();
				}else{
					
				global $_W;
				$openId=$_W['openid'];
				$users=$this->doMobileGetUserInfoByOauth();
				$nickname=$users['nickname'];
				$headimgurl=$users['headimgurl'];
				$from=htmlspecialchars($_GET['sagafrom']);

				if(isset($from) && $from!=''){

					//$openId=$_W['openid'];
					
					$users=pdo_fetch("SELECT openId FROM ims_light_users Where openId='".$openId."'");

					if($openId!='' && isset($openId)){
						$user=pdo_fetch("SELECT * FROM ims_light_users where openId='".$openId."'");
						//第一次用户进来 插入用户数据
						if(empty($user)){
							
							$userInfo=array('openId'=>$openId,'nickname'=>$nickname,'headimg'=>$headimgurl);
					    	$lookresult = pdo_insert('light_users', $userInfo);
						}
					}

					switch ($from)
					{
					//来自公众号推广的用户
					case 'dnc':
						
					    //查询此用户信息
						$fromGzh=pdo_fetch("SELECT from_dnc FROM ims_light_users Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromGzh['from_dnc'] == 0){
							
							//数据库更新data
							$from_dnc_data = array(
								    'from_dnc' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('light_users', $from_dnc_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 公众号入口进入的用户   更新 {$openId}",'actlogs');
							}
						}
					break;
					//来自国际购物中心
					case 'gwzs':

					    //查询此用户信息
						$fromGwzs=pdo_fetch("SELECT from_gwzs FROM ims_light_users Where openId='".$openId."'");
						//判断用户是否有此行为
						//次数+1
						if($fromGwzs['from_gwzs'] == 0){
							
							//数据库更新data
							$from_gwzs_data = array(
								    'from_gwzs' => 1,
							);
							//更新通过朋友分享次数信息
							$result = pdo_update('light_users', $from_gwzs_data, array('openId' => $openId));
							//更新成功
							if (!empty($result)) {

							}else{
								//没有成功 记录日志
								logs("没有成功记录此次通过 通过朋友圈推广进来的用户    更新 {$openId}",'actlogs');
							}
						}
					  break;
					  default:
					  	echo '非法操作';
					  	exit;
					  }
					 
					  include $this->template('member/yuanxiao');
				}
			}
			}
		}


	public function doMobileRealLightList(){
		$openId=$_POST['openId'];
		
		if($openId!=''){
			
			if(isset($_POST['page']) && $_POST['page']!=''){
				$page=$_POST['page'];
			}else{
				$page=1;
			}
			$pageSize=100;

			$userSql="select * from ims_light_users where openId='".$openId."'"; 
			$userInfo=pdo_fetch($userSql);
			//拿到该用户答了多少次题 节点
			$answer_number=$userInfo['answer_count'];
			//拿到用户此次进入需要展示到的页面
			$relPageFre=floor($answer_number/$pageSize);
			$relPage=$relPageFre+1;
//			$offset=$pageSize*($relPage-1); 
			$offset=$pageSize*($page-1);
			$sql="select ltid from ims_light_answerlist limit {$offset},{$pageSize}"; 
//			$sql="select ltid from ims_light_answerlist"; 
			$lightList=pdo_fetchall($sql);
			
			if(!empty($lightList)){
				$resArr=array(
					'status'=>1,
					'lightList'=>$lightList, 
					'answerNumber'=>$answer_number,
					'realPage'=>$relPage,
					'message'=>"朵朵哇！获取第{$relPage}页列表成功了",
					'user_score'=>$userInfo['score']
				);
				echo json_encode($resArr);
			}else{
				$errArr=array(
					'status'=>0,
					'lightList'=>NULL,
					'message'=>'朵朵哇！后面没有数据啦'
				);
				echo json_encode($errArr);
			}
		}
	}
	
	//活动结束以后的排行榜 根据是否得奖判断状态
	public function doMobileLuckList(){
		
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
		    // 非微信浏览器禁止浏览
		  
		    echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
		} else {
			global $_W;
			$openId=$_W['openid'];
			$WhetherLuck=0;
			
			if(isset($openId) && $openId!=''){
				$sql="select openId from ims_light_users order by score DESC limit 0,10"; 
				$LuckList=pdo_fetchall($sql);
				$LuckArr=array();
				foreach($LuckList as $k=>$v){
					$LuckArr[]=$v['openId'];
				}
				array_push($LuckArr,"oUdGzjoPd8R9ircFwk5S7AsaV0-4");
				
				if(in_array($openId, $LuckArr)){
					$WhetherLuck=1;
				}
				
//				if($openId=='oUdGzjoPd8R9ircFwk5S7AsaV0-4'){
//					$WhetherLuck=1;
//					
//				}
				$sql="select * from ims_light_users order by score DESC limit 0,10"; 
				$rankList=pdo_fetchall($sql);
				include $this->template('member/rankingList');
//				include $this->template('member/rankingList');	
				
//				if(in_array($openId, $LuckArr)){
//					$WhetherLuck=1;
//				}
//				var_dump($WhetherLuck); 
//				include $this->template('member/rankingList');	
			}else{
				echo '非法操作！';
			}
		}	
	}
	
	//保存中奖用户数据接口
	public function doMobileSaveLuck(){
		global $_W;
		$openId=$_W['openid'];
		$username=htmlspecialchars($_POST['userName']);
		$tel=htmlspecialchars($_POST['userPhone']);

		if(isset($openId) && $openId!='' && $username!='' && $tel!=''){
				$sql="select openId from ims_light_users order by score DESC limit 0,10"; 
				$LuckList=pdo_fetchall($sql);
				$LuckArr=array();
				foreach($LuckList as $k=>$v){
					$LuckArr[]=$v['openId'];
				}
				
//				if($openId=='oUdGzjoPd8R9ircFwk5S7AsaV0-4'){
	array_push($LuckArr,"oUdGzjoPd8R9ircFwk5S7AsaV0-4");
				if(in_array($openId, $LuckArr)){
					
					$luckSql="select openId from ims_light_luckuser where openId='".$openId."'"; 
					$LuckUser=pdo_fetchall($luckSql);
					
					if(empty($LuckUser)){
						$LuckInfo=array(
						'openId'=>$openId,
						'username'=>$username,
						'tel'=>$tel,
						);
						$logResult = pdo_insert('light_luckuser', $LuckInfo);
						
						if($logResult == false){
							$errmsgArr=array(
								'status'=>0,
								'message'=>'提交失败，请重新提交',
							);
							echo json_encode($errmsgArr);
							exit();
						}else{
							$msgArr=array(
								'status'=>1,
								'message'=>'恭喜你，提交成功',
							);
							echo json_encode($msgArr);
							exit();
						}
					}else{
						$HasArr=array(
								'status'=>3,
								'message'=>'已经提交过了！',
							);
						echo json_encode($HasArr);
						exit();
					}
				}else{
					$NoArr=array(
								'status'=>4,
								'message'=>'你没有中奖啦！',
							);
					echo json_encode($NoArr);
					exit();
				}
			}else{
				$nullArr=array(
								'status'=>2,
								'message'=>'不能为空啦！',
							);
				echo json_encode($nullArr);
				exit();
				
	 	}	
	}
	
	public function doMobileLuckInput(){
		global $_W;
		$openId=$_W['openid'];
		
		if(isset($openId) && $openId!=''){
				$sql="select openId from ims_light_users order by score DESC limit 0,10"; 
				$LuckList=pdo_fetchall($sql);
				$LuckArr=array();
				foreach($LuckList as $k=>$v){
					$LuckArr[]=$v['openId'];
				}
				//如果是中奖的人进入表单 渲染表单页面 如果未中奖 渲染排行榜
				
				//如果中奖的人 已经提交了信息了 那么再次进入就会进入领奖凭证页面
				//string(28) "oUdGzjoPd8R9ircFwk5S7AsaV0-4"
				
				
				 
				array_push($LuckArr,"oUdGzjoPd8R9ircFwk5S7AsaV0-4");
//				var_dump($LuckArr);
				$paiming=array_search("oUdGzjoPd8R9ircFwk5S7AsaV0-4",$LuckArr);
//				var_dump($LuckArr);

				if(in_array($openId, $LuckArr)){
					
					$finishSql="select * from ims_light_luckuser where openId='".$openId."'"; 
					$finish=pdo_fetch($finishSql);
					
//					var_dump($finish);
					if(empty($finish)){
						
						include $this->template('member/luckinput');	
					}else{
						 
						
						include $this->template('member/luckinputgot');	
					}
				
					
				}else{
					$this->doMobileRankList();  
					exit();
				}
			}else{
				echo '非法操作！';
		}
	}
	
	public function doMobileTestInput(){
		global $_W;
		var_dump($_W['openid']);
		include $this->template('member/luckinput'); 
	}
	
	/**
	 * 排行榜 活动结束以后 自动熔断到另一个排行榜 可以领取奖品
	 * 
	 */
	public function doMobileRankList(){
		
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
		    // 非微信浏览器禁止浏览'
		  
		    echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
		} else {
			$WhetherLuck=0;
			$sys=pdo_fetch("SELECT sys_on_off FROM ims_light_sys");
		
			if($sys['sys_on_off'] == 0){
				//如果熔断掉了就要换排行榜了
				$this->doMobileLuckList();
				
				exit();
			}else{
				
			$sql="select * from ims_light_users order by score DESC limit 0,10"; 
			$rankList=pdo_fetchall($sql);
			include $this->template('member/rankingList');
			}
		}
	}
	
	
	/**
	 * 灯谜活动
	 * 灯谜列表
	 * daichen
	 */
	public function doMobileLightList(){
        //如果没有page抛入 那么为首次渲染
		//如果进来有page抛入 那么属于下拉分页请求
		$openId=$_POST['openId'];
		
		if($openId!=''){
			
			if(isset($_POST['page']) && $_POST['page']!=''){
				$page=$_POST['page'];
			}else{
				$page=1;
			}
			$pageSize=10;
			
			$offset=$pageSize*($page-1); 
			$sql="select * from ims_light_answerlist limit {$offset},{$pageSize}"; 
			$lightList=pdo_fetchall($sql);
			
			if(!empty($lightList)){
				$resArr=array(
					'status'=>1,
					'lightList'=>$lightList,
					'answerNumber'=>$answer_number,
					'message'=>"朵朵哇！获取第{$page}页列表成功了"
				);
				echo json_encode($resArr);
			}else{
				$errArr=array(
					'status'=>0,
					'lightList'=>NULL,
					'message'=>'朵朵哇！后面没有数据啦'
				);
				echo json_encode($errArr);
			}
		}
		
		
    }
	
	/**
	 * 灯谜活动
	 * 判断用户答案
	 * daichen
	 */
	public function doMobileLightAnswerResult(){
		//拿到用户答案选项
		//拿到openId
		//拿到题目Id
        $user_answer=$_POST['user_answer'];
		$openId=$_POST['openId'];
		$ltid=$_POST['ltid'];
		
//		$user_answer='B';
		
//		$ltid='5';


		if($user_answer!='' && $openId!='' && $ltid!=''){
			//拿到此题目的信息 正确答案选项 & 题目对应分数
			
			$a_sql="select * from ims_light_answerlist where ltid={$ltid}";
			
			$answerInfo=pdo_fetch($a_sql);
			$answerScore=$answerInfo['score'];	
			
			
			
//			var_dump($answerInfo);
//			var_dump($user_answer);
	
			//查询送分题库ID
//			$whateverSql="select ltid from ims_light_answerlist where whatever_answer=1";
//			$whateverAnswerList=pdo_fetchall($whateverSql); 
			$whateverAnswerList=array('9','24');

			
			//如果此题存在于送分题库
			if(in_array($ltid, $whateverAnswerList)){
				try {
	    			pdo_begin();	 
					//记录用户此次答题信息到log表
					$answerWhateverLogInfo=array(
							'openId'=>$openId,
							'ltid'=>$ltid,
							'answer_title'=>$answerInfo['title'],
							'user_answer'=>$user_answer,
							'result'=>1,
							'is_whatever'=>1  //是否是送分
					);
					
	    			$logResult = pdo_insert('light_answerlog', $answerWhateverLogInfo);
	    			if ($logResult == false) {
	    				throw new Exception('记录用户答题记录失败');
	    			}
					
					//拿到此用户的信息
					$sql="select * from ims_light_users where openId='".$openId."'"; 
					$userInfo=pdo_fetch($sql);
					//更新用户信息
					$answer_data=array(
							'answer_count'=>$userInfo['answer_count']+1,
							'right_count'=>$userInfo['right_count']+1,
							'score'=>$userInfo['score']+$answerScore
					);
						
					$updataRes=pdo_update('light_users', $answer_data, array('openId' => $openId));
					if ($updataRes == false) {
	    				throw new Exception('更新用户抽奖信息失败');
	    			}
	    			pdo_commit();
	    			$answerMessage=array(
						'status'=>1,
						'message'=>"这是送分题啦！！恭喜你答对了+{$answerScore}分！",
						'nextAnswer'=>$ltid+1,
						'user_score'=>$userInfo['score']+$answerScore
					);		
					echo json_encode($answerMessage);
	    			exit();
					
		    		} catch (Exception $e) {
		    			pdo_rollback();
//						$errMessage=array(
//							'status'=>-1,
//							'message'=>'事务失败啦，朵朵 送分题',
//							'nextAnswer'=>''
//						);	
//						echo json_encode($errMessage);
		    			exit();
		    		}
			}else{
				//如果用户答案和该题目的答案符合 那么正确 事务处理SQL
				
				if($user_answer == $answerInfo['answer_option']){
					
					
					try {
						
		    			pdo_begin();
						
						//记录用户此次答题信息到log表
						$answerLogInfo=array( 
								'openId'=>$openId,
								'ltid'=>$ltid,
								'answer_title'=>$answerInfo['title'],
								'user_answer'=>$user_answer,
								'result'=>1,
								'is_whatever'=>0  //是否是送分
						);
						
		    			$logResult = pdo_insert('light_answerlog', $answerLogInfo);
		    			
		    			if ($logResult == false) {
		    				throw new Exception('记录用户答题记录失败');
		    			}
						
						//拿到此用户的信息
						$sql="select * from ims_light_users where openId='".$openId."'"; 
						$userInfo=pdo_fetch($sql);
						//更新用户信息
						$answer_data=array(
								'answer_count'=>$userInfo['answer_count']+1,
								'right_count'=>$userInfo['right_count']+1,
								'score'=>$userInfo['score']+$answerScore
						);
						
						
						//$updataRes = "UPDATE `ims_light_users` SET `answer_count`='".$userInfo['answer_count']."',`right_count`='".$userInfo['right_count']."',`score`='".$userInfo['score']."' WHERE `openId`='".$openId."'";
//          			echo $updataRes;
            			//$updataRes=pdo_query($updataRes);	
						$updataRes=pdo_update('light_users', $answer_data, array('openId' => $openId));
						if ($updataRes == false) {
		    				throw new Exception('更新用户抽奖信息失败');
		    			}
		    			pdo_commit();

		    			$answerMessage=array(
							'status'=>1,
							'message'=>"恭喜你答对了+{$answerScore}分！",
							'nextAnswer'=>$ltid+1,
							'user_score'=>$userInfo['score']+$answerScore,
							'answer_option'=>$answerInfo['answer_option']
						);		
						echo json_encode($answerMessage);
		    			exit();
			    		} catch (Exception $e) {
			    			pdo_rollback();
							
//							$errMessage=array(
//								'status'=>-1,
//								'message'=>'事务失败啦，朵朵 不是送分题',
//								'nextAnswer'=>''
//							);	
//							echo json_encode($errMessage);
			    			exit();
			    		}
				}else{	
					try {
		    			pdo_begin();
						
						//记录用户此次答题信息到log表
						$answerWrongLogInfo=array(
								'openId'=>$openId,
								'ltid'=>$ltid,
								'answer_title'=>$answerInfo['title'],
								'user_answer'=>$user_answer,
								'result'=>0,
								'is_whatever'=>0  //是否是送分
						);
						
		    			$logResult = pdo_insert('light_answerlog', $answerWrongLogInfo);
		    			if ($logResult == false) {
		    				throw new Exception('记录用户答题记录失败');
		    			}
						
						//拿到此用户的信息
						$sql="select * from ims_light_users where openId='".$openId."'"; 
						$userInfo=pdo_fetch($sql);
						//更新用户信息 打错了减分
						$answer_data=array(
								'answer_count'=>$userInfo['answer_count']+1,
								'wrong_count'=>$userInfo['wrong_count']+1,
								'score'=>$userInfo['score']-$answerScore
						);
							
						$updataWrongRes=pdo_update('light_users', $answer_data, array('openId' => $openId));
						
						if ($updataWrongRes == false) {
		    				throw new Exception('更新用户抽奖信息失败');
		    			}
		    			pdo_commit();
	
		    			$answerWrongMessage=array(
							'status'=>0,
							'message'=>"很遗憾你打错了-{$answerScore}分！",
							'nextAnswer'=>$ltid+1,
							'user_score'=>$userInfo['score']-$answerScore,
							'answer_option'=>$answerInfo['answer_option']
						);		
						
						echo json_encode($answerWrongMessage);
		    			exit();
			    		} catch (Exception $e) {
			    		
			    			pdo_rollback();
							
//							$errWrongMessage=array(
//								'status'=>-1,
//								'message'=>'事务失败啦，朵朵sss',
//								'nextAnswer'=>''
//							);	
//							echo json_encode($errWrongMessage);
			    			exit();
			    		}
					
				}
			}
			
			
			
			
		} 
		
    }

	public function doMobileLightTimeOut(){
		$ltid=$_POST['ltid'];
		$openId=$_POST['openId'];
		
		if($ltid!='' && $openId!=''){
		$a_sql="select * from ims_light_answerlist where ltid={$ltid}";
			
		$answerInfo=pdo_fetch($a_sql);
		$answerScore=$answerInfo['score'];

		try {
			pdo_begin();
			
			//记录用户此次答题信息到log表
			$answerTimeOutInfo=array(
					'openId'=>$openId,
					'ltid'=>$ltid,
					'answer_title'=>'超时了',
					'user_answer'=>'timeout',
					'result'=>0,
					'is_whatever'=>0  //是否是送分
			);
			
			$logTimeOutResult = pdo_insert('light_answerlog', $answerTimeOutInfo);
			if ($logTimeOutResult == false) {
				throw new Exception('记录用户答题记录失败');
			}
			
			//拿到此用户的信息
			$TimeOutSql="select * from ims_light_users where openId='".$openId."'"; 
			$userInfo=pdo_fetch($TimeOutSql);
			//更新用户信息 超时减分
			$answer_data=array(
					'answer_count'=>$userInfo['answer_count']+1,
					'wrong_count'=>$userInfo['wrong_count']+1,
					'score'=>$userInfo['score']-$answerScore
			);
				
			$updataTimeOutRes=pdo_update('light_users', $answer_data, array('openId' => $openId));
			
			if ($updataTimeOutRes == false) {
				throw new Exception('更新用户抽奖信息失败');
			}
			pdo_commit();

			$answerBomMessage=array(
				'status'=>9,
				'message'=>'超时',
				'nextAnswer'=>$ltid+1,
				'user_score'=>$userInfo['score']-5,
				'answer_option'=>$answerInfo['answer_option']
			);		
			
			echo json_encode($answerBomMessage);
			exit();
    	} catch (Exception $e) {
    		pdo_rollback();
//				$errWrongMessage=array(
//					'status'=>-1,
//					'message'=>'事务失败啦，朵朵',
//					'nextAnswer'=>''
//				);	
//				echo json_encode($errWrongMessage);
    			exit();
    	}
		}
		
		
	}
    
    /**
	 * 灯谜活动
	 * 拿到题目信息
	 * daichen
	 */
    public function doMobileAnswerInfo(){
//  	header('Access-Control-Allow-Origin:*');
//		header('Access-Control-Allow-Methods:POST,GET');
		$ltid=$_POST['ltid'];
		//这里还要给我openId
		$openId=$_POST['openId'];
		
		//炸弹库
		$bomb=array(
//			'12','31','83'
		);
		$smiling=array(
//			'3','47','78'
		);
		

		if($ltid!='' && isset($ltid)){
			
			if(in_array($ltid, $bomb)){
				
				try {
	    			pdo_begin();
					
					//记录用户此次答题信息到log表
					$answerBomInfo=array(
							'openId'=>$openId,
							'ltid'=>$ltid,
							'answer_title'=>'炸弹题',
							'user_answer'=>'bom',
							'result'=>0,
							'is_whatever'=>0  //是否是送分
					);
					
	    			$logBomResult = pdo_insert('light_answerlog', $answerBomInfo);
	    			if ($logBomResult == false) {
	    				throw new Exception('记录用户答题记录失败');
	    			}
					
					//拿到此用户的信息
					$BomSql="select * from ims_light_users where openId='".$openId."'"; 
					$userInfo=pdo_fetch($BomSql);
					//更新用户信息 炸弹减分
					$answer_data=array(
							'answer_count'=>$userInfo['answer_count']+1,
							'wrong_count'=>$userInfo['wrong_count']+1,
							'score'=>$userInfo['score']-5
					);
						
					$updataBomRes=pdo_update('light_users', $answer_data, array('openId' => $openId));
					
					if ($updataBomRes == false) {
	    				throw new Exception('更新用户抽奖信息失败');
	    			}
	    			pdo_commit();

	    			$answerBomMessage=array(
						'status'=>3,
						'message'=>'很遗憾你碰到了雷！-5分！',
						'nextAnswer'=>$ltid+1,
						'user_score'=>$userInfo['score']-5
					);		
					
					echo json_encode($answerBomMessage);
	    			exit();
		    	} catch (Exception $e) {
		    		pdo_rollback();
//						$errWrongMessage=array(
//							'status'=>0,
//							'message'=>'事务失败啦，朵朵',
//							'nextAnswer'=>''
//						);	
//						echo json_encode($errWrongMessage);
		    			exit();
		    	}
			}


			if(in_array($ltid, $smiling)){
				
				try {
	    			pdo_begin();
					
					//记录用户此次答题信息到log表
					$answerSmileInfo=array(
							'openId'=>$openId,
							'ltid'=>$ltid,
							'answer_title'=>'笑脸题',
							'user_answer'=>'smiling',
							'result'=>1,
							'is_whatever'=>0  //是否是送分
					);
					
	    			$logResult = pdo_insert('light_answerlog', $answerSmileInfo);
	    			if ($logResult == false) {
	    				throw new Exception('记录用户答题记录失败');
	    			}
					
					//拿到此用户的信息
					$smilSql="select * from ims_light_users where openId='".$openId."'"; 
					$userInfo=pdo_fetch($smilSql);
					//更新用户信息 笑脸加分
					$answer_smil_data=array(
							'answer_count'=>$userInfo['answer_count']+1,
							'right_count'=>$userInfo['right_count']+1,
							'score'=>$userInfo['score']+5
					);
						
					$updataSmileRes=pdo_update('light_users', $answer_smil_data, array('openId' => $openId));
					
					if ($updataSmileRes == false) {
	    				throw new Exception('更新用户抽奖信息失败');
	    			}
	    			pdo_commit();

	    			$answerSmileMessage=array(
						'status'=>4,
						'message'=>'恭喜你碰到个金元宝！+5分！',
						'nextAnswer'=>$ltid+1,
						'user_score'=>$userInfo['score']+5
					);		
					
					echo json_encode($answerSmileMessage);
	    			exit();
		    		} catch (Exception $e) {
		    			pdo_rollback();
//						$errWrongMessage=array(
//							'status'=>0,
//							'message'=>'事务失败啦，朵朵',
//							'nextAnswer'=>''
//						);	
//						echo json_encode($errWrongMessage);
		    			exit();
		    		}
			}
			
			$sql="select * from ims_light_answerlist where ltid={$ltid}"; 
			$answerInfo=pdo_fetch($sql);
			
			if(!empty($answerInfo)){
				$optionSql="select loptions,options_content from ims_light_answeroption where ltid={$ltid}";
				$optionInfo=pdo_fetchAll($optionSql);
				
				$answerInfo=array(
					'status'=>1,
					'answerInfo'=>$answerInfo,
					'answerOption'=>$optionInfo,
//					'score'=>$userInfo['score']
				);
				echo json_encode($answerInfo);
			}else{
				$answerNoInfo=array(
				'status'=>2,
				'answerInfo'=>'',
				'answerOption'=>'',
				'message'=>'没有题啦！！！'
				);
				echo json_encode($answerNoInfo);
			}
			
		}else{
			$answerErrInfo=array(
				'status'=>0,
				'answerInfo'=>'',
				'answerOption'=>''
			);
			echo json_encode($answerErrInfo);
		}
    }
	
	
	
	
	
	// +----------------------------------------------------------------------
	// | 猜灯谜 END daichen
	// +----------------------------------------------------------------------
	
	

    public function doMobileLeaveInfo($platenumber = '陕A57A53')
    {
        $lastEntry = pdo_fetch("SELECT `create_time` FROM `ims_park_entry` WHERE `platenumber` = '".$platenumber."' ORDER BY `create_time` DESC LIMIT 1");

        var_dump($lastEntry);

        $leaveCharge = pdo_fetch("SELECT `platenumber`,`leave_time`,`amount` FROM `ims_park_leave` WHERE `platenumber` ='".$platenumber."' AND (`create_time` BETWEEN ".$lastEntry['create_time']." AND ".time()." )");

        var_dump($leaveCharge);
    }
	
	//会员卡激活
	public function doMobileiactCardFree($openId,$code,$cardId){
		
		$sql="select idcard from ims_mc_mapping_fans as f 
		left join ims_mc_members as m on f.uid = m.uid where f.openid = '{$openId}'";
	
	
		$localInfo = pdo_fetch($sql);
	
		$erpClient = new CardApi();
		$erpData=array(
			'idNum'=>$localInfo['idcard'],
		);
		//查询ERP会员信息主键
		$erpInfoCustId = $erpClient->getCustomerCustID($erpData);
		//查询ERP会员信息
		$erpInfo = $erpClient->getCustomerInfo($erpInfoCustId);
	
	
		$token=file_get_contents("http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=getToken&m=member");
	
		$sendapi = "https://api.weixin.qq.com/card/membercard/activate?access_token={$token}";
	
		$score = $erpClient->getCustomerScore($erpInfoCustId);
	
		if(empty($score)){
			$score=0;
		}
	
		if(empty($card_id)){
			$card_id=0;
		}
		
		$init_bonus='0';
		$membership_number='601000355029';
		
		$code='080244468422';
		$card_id='pUdGzjq61Sbn99WDGAuTvUDk5d8g';
		
	
		$data = array(
		    'init_bonus' => $init_bonus,
		    'membership_number'=>$membership_number,
		    'code'=>$code,
	        'card_id'=>$card_id,
		);
	
		$jsondata = json_encode($data);
	
		load()->func('communication');
		$response = http_attach_post($sendapi, $jsondata);
	
		var_dump($response);
	
	/*
		$responses = json_encode($response['content']);
	
		$string = (string )$responses;
	
						$message = array();
	                    $message['test'] = $jsondata;
						$message['openid']=$cardId;
						$message['code']= $code;
						$message['cardid']= $cardId;
						$result = pdo_insert('wechat_code', $message);
	 
	 */
	}


    public function doMobileBaseInfo()
    {

        $plateNumber = '陕A278JD';
        $server ="paypark";  //服务器IP地址,如果是本地，可以写成localhost
        //$server ="192.168.8.253";  //服务器IP地址,如果是本地，可以写成localhost
	    $username ="sa";  //用户名
	    $pwd ="Lf0507"; //密码
	    //$database ="ACS_Parkin";  //数据库名称

	    //进行数据库连接
	    $conn = mssql_connect($server,$username,$pwd) or die ("connect failed");

	    var_dump($conn);
	    //mssql_select_db($database,$conn);
	    //查询入场记录
	    //$sql = "SELECT  *,convert(varchar(19),Crdtm,121) AS inTime  FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' and Crdtm between cast('".date("Y-m-d")." 00:00:00' as datetime) and cast('".date("Y-m-d")." 23:59:59' as datetime) ";
        //$sql = "SELECT TOP 1  *, convert(varchar(19),Crdtm,121) AS inTime FROM Tc_UserCrdtm_In WHERE CarCode = '".$plateNumber."' ORDER BY Crdtm DESC";
        $sql = "SELECT TOP 1  *, convert(varchar(19),Crdtm,121) AS inTime FROM Tc_UserCrdtm_In ORDER BY Crdtm DESC";
        //$sql = "SELECT TOP 1  *, convert(varchar(19),Crdtm,121) AS inTime FROM Tc_UserCrdtm_In where CarCode = '".$plateNumber."' ORDER BY Crdtm DESC";

	    $sql = iconv('utf-8', 'gb2312', $sql);
	    $rs = mssql_query($sql);
	    $row = mssql_fetch_assoc($rs);

        var_dump($row);

        $date = strtotime($row['inTime']);
        //$inTime = $row['inTime'];

        //$leaveCharge = pdo_fetch("SELECT `platenumber`,`leave_time`,`amount` FROM `ims_park_leave` WHERE `platenumber` ='".$plateNumber."' AND (`create_time` BETWEEN ".$date." AND ".time()." )");


        //$res = $this->getPlateNumberOutTime($plateNumber, $row['inTime']);

        //var_dump($res);

    }

    /*
     *
     * 3.8妇女节活动         begin
     *
     *
     */

    /*
     *
     * 3.8 妇女节活动   链接
     * @women_on_off  1 活动结束
     *                2 活动繁忙
     *
     */

     
    public function doMobileRoseduo(){

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
            echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/road38/images/cardhead.png"/>';
            echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
        } else {
            $sys=pdo_fetch("SELECT women_on_off FROM ims_mc_womenswitch");

            if($sys['women_on_off'] == 1){
                include $this->template('member/roadending');
                exit();
            }else if($sys['women_on_off'] == 2){
                include $this->template('member/roadwrong');
                exit();
            }
            else {
                global $_W;
                $typea=$_GET['typea'];
                $openid = $_W['openid'];
                $userip=$_SERVER['REMOTE_ADDR'];
                $info=pdo_fetch("SELECT * FROM ims_mc_ownerinfo WHERE openid ='{$openid}'");
                $card=pdo_fetch("SELECT * FROM ims_mc_womencard WHERE openid ='{$openid}'");

                if(!empty($info) && !empty($card)){
                    $code= $card['code'];
                    include $this->template('member/womenopencard');
                }else{
                    include $this->template('member/38road');
                }
            }
        }
    }


    /**
     *
     * 3.8妇女节活动   活动详情
     *
     */
    public function doMobileRosechen(){
    	include $this->template('member/roadrule');
    }


    /**
     *
     * 3.8妇女节活动   开关接口
     * if(women_on_off=0) 关  else  开
     */
    public function doMobilewomenswitch()
    {
        if (!empty($_POST['key'])) {
            $key = $_POST['key'];
            $up = pdo_update('work_switch', array('work_switch' => $key), array('id' => 1));
            if ($up) {
                $response = array('status' => 0, 'errmsg' => 'ok');
            } else {
                $response = array('status' => -1, 'errmsg' => 'fail');
            }

        }else{
                $response = array('status' => 1, 'errmsg' => 'please input switchkeys');
        }
        echo json_encode($response);
    }

    public function doMobileswitchcase()
    {
        include $this->template('member/womenday');
    }


    /**
     *  3.8妇女节活动  模版消息链接页面
     */

   /* public function doMobileWomenOpenCard(){
        global  $_W;
        $openid=$_W['openid'];

    }*/

    /*
     *
     *3.8 妇女节活动  进入链接身份判断接口
     *
     */
    public function doMobileJudgStatus(){

        $openid=$_POST['openid'];
        $sql="SELECT name,tel,code,platenumber,idNum,create_time,get_time FROM ims_work_ownerinfo as A left join ims_work_card as B on A.openid=B.openid  where A.openid='{$openid}'";
        $result=pdo_fetch($sql);
        if(empty($result['create_time']))
        {
            $response=array('status'=>0,'errmsg'=>'ok');
        }elseif (!empty($result['create_time']) && empty($result['get_time'])){
            $response=array('status'=>1,'name'=>$result['name'],
                            'tel'=>$result['tel'],
                            'idNum'=>$result['idNum'],
                            'platenumber'=>$result['platenumber']);
        }else{
            $response=array('status'=>2,'name'=>$result['name'],
                            'tel'=>$result['tel'],
                            'idNum'=>$result['idNum'],
                            'platenumber'=>$result['platenumber']);
        }
        echo  json_encode($response);

    }


    /***
     * 5.1 劳动节活动 用户数据插入接口
     */
    public function doMobileGetOwnerinfo(){

        $openid     =$_POST['openid'];
        $ownername  =htmlspecialchars($_POST['userName']);
        $ownertel   =$_POST['userPhone'];
        $platenumber=str_replace(' ','',$_POST['userCar']);
        $idNum      =$_POST['idNum'];
        $isAgree    =$_POST['isAgree'];
        $typea      =$_POST['typea'];

            $plateinfo = pdo_fetch("SELECT * FROM ims_work_ownerinfo where platenumber='{$platenumber}'");
            $telinfo = pdo_fetch("SELECT * FROM ims_work_ownerinfo where tel='{$ownertel}'");
            $idNuminfo = pdo_fetch("SELECT * FROM ims_work_ownerinfo where idNum='{$idNum}'");
            if (!empty($telinfo)) {

                $response = array('status' => 1, 'errmsg' => 'AlreadyTel');

            } elseif (!empty($plateinfo)) {

                $response = array('status' =>2, 'errmsg' => 'AlreadyPlate');
            } elseif(!empty($idNuminfo)){

                $response = array('status' => 3, 'errmsg' => 'AlreadyidNum');
            }else{
                $data = array(
                    'openid' => $openid,
                    'name' => $ownername,
                    'tel' => $ownertel,
                    'idNum'=>$idNum,
                    'platenumber' => $platenumber,
                    'create_time' => time(),
                    'gzh'=>$typea,
                );
                $insert = pdo_insert('work_ownerinfo', $data);
                if ($insert) {
                    if($isAgree){
                        $response=$this->Workmember($ownername,$ownertel,$idNum,$openid);
                    }else{
                        $response = array('status' => 0, 'errmsg' => 'ok');
                    }
                }else{
                        $response = array('status' => 7, 'errmsg' => '信息插入失败');
                }
            }

        $response=json_encode($response);
        echo $response;
    }


    /**
     *
     * 3.8 拉起卡券 微信签名参数
     *
     */
    public function doMobileWomenCardSignInfo(){
        global $_W;
        $timestamp=$_W['timestamp'];
        $cticket=$this->doMobileGetCardS();
        $card_id='pUdGzjkDma4wqg8jcmQrct3023qU';

        $nonce_str=$this->generateNonceStr();
        $card = array(
            $timestamp,
            $cticket,
            $card_id,
            $nonce_str
        );
        sort($card,SORT_STRING);
        foreach($card as $k=>$v){
            $return .= $v;
        }

        $sign=sha1($return);
        $res=array(
            'timestamp'=>$timestamp,
            'signature'=>$sign,
            'noncestr'=>$nonce_str,
        );

        echo json_encode($res);
    }

    /**
     *
     * 3.8妇女节活动 地理位置获取
     *
     */
    public function doMobileGetLocation(){
        $ip=$_POST['userip'];
        $gzh=$_POST['typea'];
        $openid=$_POST['openid'];
        $lat=$_POST['latitude'];    //纬度
        $lng=$_POST['longitude'];

        if (!empty($lat) && !empty($lng)) {
                $location = file_get_contents("http://apis.map.qq.com/ws/geocoder/v1/?location=" . $lat . "," . $lng . "&key=IBMBZ-5DRW3-NV535-YLCTQ-P7AM2-5PFP6");
                $location = json_decode($location, true);
                $province = $location['result']['address_component']['province'];
                $city = $location['result']['address_component']['city'];
                $district = $location['result']['address_component']['district'];
                $street = $location['result']['address_component']['street'];
                $street_num = $location['result']['address_component']['street_number'];
                $insert = pdo_insert('work_getlocation', array(
                    'openid' => $openid,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'province' => $province,
                    'city' => $city,
                    'district' => $district,
                    'street' => $street,
                    'street_num' => $street_num,
                    'ip'=>$ip,
                    'gzh'=>$gzh,
                    'time' => time(),

                ));
                if ($insert) {
                    $response = array("status" => 0, "city" => $city);
                } else {
                    $response = array('status' => 2, 'errmsg' => '位置获取失败');
                }


        } else {
            $response = array('status' => 1, 'errmsg' => '请给我经纬度');
        }

        echo json_encode($response);
    }

    /**
     *
     * 3.8 妇女节活动  转发统计接口
     *
     */
    public function doMobileFriendShare(){
        $openid=$_POST['openid'];
        $gzh=$_POST['typea'];
        if(!empty($openid)){
            $openidinfo=pdo_fetch("select * from ims_work_share where openid='{$openid}' and gzh={$gzh}");
            if(empty($openidinfo)){
               $share=pdo_insert('work_share',array(
                    'openid'=>$openid,
                    'friend'=>1,
                    'quan'=>0,
                    'gzh'=>$gzh,
                ),array('openid'=>$openid));

            }else {
                $share=pdo_update('work_share',array(
                    'friend'=>empty($openidinfo['friend'])?1:$openidinfo['friend']+1,
                ),array('openid'=>$openid));
            }
            if($share){
                $response=array('status'=>0,'errmsg'=>'ok');
            }else{
                $response=array('status'=>1,'errmsg'=>'fail');
            }
        }
        echo json_encode($response);
    }

    public function doMobilequanShare(){
        $openid=$_POST['openid'];
        $gzh=$_POST['typea'];
        if(!empty($openid)){
            $openidinfo=pdo_fetch("select * from ims_work_share where openid='{$openid}' and gzh={$gzh} ");
            if(empty($openidinfo)){
                $share=pdo_insert('work_share',array(
                    'openid'=>$openid,
                    'friend'=>0,
                    'quan'=>1,
                    'gzh'=>$gzh,
                ),array('openid'=>$openid));

            }else {
                $share=pdo_update('work_share',array(
                    'quan'=>empty($openidinfo['quan'])?1:$openidinfo['quan']+1,
                ),array('openid'=>$openid));
            }
            if($share){
                $response=array('status'=>0,'errmsg'=>'ok');
            }else{
                $response=array('status'=>1,'errmsg'=>'fail');
            }
        }
        echo json_encode($response);
    }

    /*
     *
     * 3.8 妇女节活动   验证码校验
     *
     */
    public function doMobilePhoneJudge()
    {
        $yzm=$_POST['yzm'];
        $tel = $_POST['userPhone'];

        if (!empty($tel) && !empty($yzm)) {
            $sql="SELECT *  FROM `ims_core_cache` WHERE `key` LIKE '".$tel."%' limit 0,1 ";
            $result = pdo_fetch($sql);
            //$data['tel'] = $result[0]['key'];
            $data= unserialize($result['value']);
           if($data['code'] == $yzm)
           {
               $response=array('status'=>0,'errmsg'=>'ok');
           }else{
               $response=array('status'=>2,'errmsg'=>'验证码错误');
           }
        }else{
            $response=array('status'=>1,'errmsg'=>'手机号与验证码都不能为空');
        }
        echo json_encode($response);
    }

    /**
     *
     * 3.8 妇女节活动   手机发送验证码
     *
     *
     */
    public  function doMobileSendPhone(){
        global $_GPC;
        load()->func('cache.mysql');
        $tel  = $_GPC['phone'];
        $code = random(6,true);
        $msg  = "【西安赛格】短信验证码为：" . $code . ",请您在30分钟内完成,如非本人操作,请忽略。退订回TD";
        $preg = preg_match('/^\d{11}$/',$tel);
        if(!$preg  || empty($tel)){
            echo json_encode(array('status'=>0,'text'=>'手机号必填或格式不正确'));
            exit();
        }
        $cache = cache_load($_GPC['phone']);
        if(empty($cache)){
            cache_write($_GPC['phone'],array('code'=>$code,'create_time'=>(TIMESTAMP+60)));
            $this->sendmsg($tel,$msg);
            echo json_encode(array('status'=>1,'text'=>'短信已发送，请注意查收'));
            exit();
        }else{
            //已过期
            if($cache['create_time'] < TIMESTAMP){
                cache_write($_GPC['phone'],array('code'=>$code,'create_time'=>(TIMESTAMP+60)));
                $this->sendmsg($tel,$msg);
                echo json_encode(array('status'=>1,'text'=>'短信已发送，请注意查收'));exit();
            }else{
                //未过期
                echo json_encode(array('status'=>2,'text'=>'您好，验证码时效为10分钟，请直接填写现有验证码。'));exit();
            }
        }
    }

    public function doMobiletestwomen()
    {
        include $this->template('member/roadending');
    }
    /**
     *
     *3.8 妇女节活动   end
     *
     */

    /////////////////////////数据中转中心//////////////////////////////
    /************************peace&Love******************************/
    /**
     * @return array|mixed|stdClass
     * @API 获得客户积分
     * @author daichen
     */
    public function apiGetCustScore($scoreParam){
//        $scoreParam=array(
//            'custId'=>$card['custid'],
//        );
        $host=$this->apiHost;
        $scoreUrl=$host.'?service=Customer.GetCustScore';
        $scores=$this->http_post_attach($scoreUrl,$scoreParam);
        $scoreArr=json_decode($scores,true);
        return $scoreArr;
        //$score=$scoreArr['data']['data']['scoreList'];
    }

    /**
     * @param $infoParam
     * @return array|mixed|stdClass
     * @API 获得客户信息
     * @author daichen
     */
    public function apiGetCustInfo($infoParam){
          //参数格式 array
//        $infoParam=array(
//            'custId'=>$card['custid'],
//        );
        $host=$this->apiHost;
        $infoUrl=$host.'?service=Customer.GetCustInfo';
        $custInfo=$this->http_post_attach($infoUrl,$infoParam);
        $custInfoArr=json_decode($custInfo,true);
        return $custInfoArr;
        //$cardId=$custInfoArr['data']['info']['cardId'];  //保留方便取数据
    }

    /**
     * @param $scoreParam
     * @return array|mixed|stdClass
     * @API 设置用户积分
     * @author daichen
     */
    public function apiSetCustScore($scoreParam){
//        $scoreParam=array(
//            'cardId'=>$_GPC['cardId'],
//            'scoreNum'=>-$_GPC['score'],
//        );
        $host=$this->apiHost;
        $scoreUrl=$host.'?service=Customer.SetCustScore';
        $scores=$this->http_post_attach($scoreUrl,$scoreParam);
        $scoreArr=json_decode($scores,true);
        return $scoreArr;
        //$score=$scoreArr['data']['data']['scoreList'];
    }

    /**
     * @param $parkParam
     * @return array|mixed|stdClass
     * @API 使用立方WebService 获取会员车牌号码
     * @author daichen
     */
    public function apiGetPlateNumber($parkParam){
//        $parkParam = array(
//            'plateNumber' => $platenumbers,
//        );
        $host=$this->apiHost;
        $parkUrl = $host . '?service=Park.GetPlateNumber&plateNumber';
        $parkInfo = $this->http_post_attach($parkUrl, $parkParam);
        $parkInfoArr = json_decode($parkInfo, true);
        return $parkInfoArr;
        //$prakInfoData = $parkInfoArr['data']['data'];
    }

    /**
     * @param $addParkParam
     * @return array|mixed|stdClass
     * @API 使用立方WebService 添加车牌号码
     * @author daichen
     */
    public function apiAddPlateNumber($addParkParam)
    {
//        $addParkParam = array(
//            'plateNumber' => $platenumbers,
//        );
        $host=$this->apiHost;
        $addParkUrl = $host . '?service=Park.AddPlateNumber&plateNumber';
        $addParkInfo = $this->http_post_attach($addParkUrl, $addParkParam);
        $addParkInfoArr = json_decode($addParkInfo, true);
        return $addParkInfoArr;
        //$addParkInfoArr['data']
    }

    /**
     * @param $DeleteParam
     * @return array|mixed|stdClass
     * @API 使用立方WebService 删除车牌号码
     * @author daichen
     */
    public function apiDeletePlateNumber($DeleteParam){
//        $DeleteParam = array(
//            'plateNumber' => $platenumbers,
//        );
        $host=$this->apiHost;
        $deleteParkUrl = $host . '?service=Park.DeletePlateNumber&plateNumber';
        $deleteParkInfo = $this->http_post_attach($deleteParkUrl, $DeleteParam);
        $deleteParkInfoArr = json_decode($deleteParkInfo, true);
        return $deleteParkInfoArr;
    }

    /**
     * @param $GetAcsParam
     * @return array|mixed|stdClass
     * @API 查询车牌最近一条的入场记录
     * @author daichen
     */
    public function apiGetAcsRecord($GetAcsParam){
//        $GetAcsParam = array(
//            'plateNumber' => $platenumbers,
//        );
        $host=$this->apiHost;
        $getAscParkUrl = $host . '?service=Park.GetAcsRecord&plateNumber';
        $getAscParkInfo = $this->http_post_attach($getAscParkUrl, $GetAcsParam);
        $getAscParkInfoArr = json_decode($getAscParkInfo, true);
        return $getAscParkInfoArr;
    }

    /**
     * @param $OutTimeParam
     * @return array|mixed|stdClass
     * @API 根据时间参数，查询车牌的离场记录
     * @author daichen
     */
    public function apiGetAcsRecordOutTime($OutTimeParam){
//      $OutTimeParam = array(
//            'plateNumber' => $platenumber,
//              'outTime'=>$outTime,
//      );
        $host=$this->apiHost;
        $getAscParkUrl = $host . '?service=Park.GetAcsRecordOutTime&plateNumber';
        $getAscParkInfo = $this->http_post_attach($getAscParkUrl, $OutTimeParam);
        $getAscParkInfoArr = json_decode($getAscParkInfo, true);
        return $getAscParkInfoArr;
    }

    /**
     * @param $RegParam
     * @return array|mixed|stdClass
     * @API 注册会员信息
     * @author daichen
     */
    public function apiCustRegsiter($RegParam){
//      $OutTimeParam = array(
//         'name' => $name,
//         'idNum'=>$idNum,
//          'tel'=>$tel
//      );
        $host=$this->apiHost;
        $RegUrl = $host . '?service=Customer.CustRegsiter&plateNumber';
        $RegInfo = $this->http_post_attach($RegUrl, $RegParam);
        $RegInfoArr = json_decode($RegInfo, true);
        return $RegInfoArr;
    }


    public function doMobileTestApi()
    {
        $name='家扥';
        $idNum='440901098301218452';
        $tel= '17227777467';



        $RegParam=array(
            'name'=>$name,
            'idNum'=>$idNum,
            'tel'=>$tel,
            'idType'=>'H'
        );


        $custInfo = $this->apiCustRegsiter($RegParam);


        var_dump($custInfo);
    }
	
	/**
	 * 赛格招聘
	 */
//	public function doMobileSagaRecruit(){
//		$RecruitInfo=pdo_fetchall("select * from ims_act_recruit");
//		$json=json_encode($RecruitInfo);
//		echo $json;
//	}
	public function doMobileInviteEntry(){
		
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
			header("Location: http://wx.cnsaga.com/1219/join/index.html"); 
		  
		} else {
			
			global $_W;
			$specil=htmlentities($_GET['specil']);
			switch ($specil) {
				case 'rsxz':
					include $this->template('member/rsxz');	
					break;
				case 'cw':
					include $this->template('member/cw');	
					break;
				case 'scgl':
					include $this->template('member/scgl');	
					break;
				case 'ITgl':
					include $this->template('member/ITgl');	
					break;		
				case 'wy':
					include $this->template('member/wy');	
					break;
				case 'aql':
					include $this->template('member/aql');	
					break;	
				case 'ncgs':
					include $this->template('member/ncgs');	
					break;
				case 'syy':
					include $this->template('member/syy');	
					break;
				default:
					include $this->template('member/invite');	
					break;
			}
		}
	}
	
	public function doMobileInviteOrangeEntry(){
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'MicroMessenger') === false) {
			header("Location: http://wx.cnsaga.com/1219/join/index.html"); 
		  
		} else {
			
		$specil=htmlentities($_GET['specil']);
		global $_W;
		switch ($specil) {
			case 'rsxz_chen':
				include $this->template('member/rsxz_chen');	
				break;
			case 'cw_chen':
				include $this->template('member/cw_chen');	
				break;
			case 'scgl_chen':
				include $this->template('member/scgl_chen');	
				break;
			case 'ITgl_chen':
				include $this->template('member/ITgl_chen');	
				break;		
			case 'wy_chen':
				include $this->template('member/wy_chen');	
				break;
			case 'aql_chen':
				include $this->template('member/aql_chen');	
				break;	
			case 'ncgs_chen':
				include $this->template('member/ncgs_chen');	
				break;
			case 'syy_chen':
				include $this->template('member/syy_chen');	
				break;
			default:
				include $this->template('member/invite_chen');	
				break;
		}
	}
	}

	public function doMobileBecomevip(){
		global $_W;
		include $this->template('member/becomevip');	
		
	}

    /*
     *
     * 5.1 劳动节活动     begin
     *
     *
     */
    /**
     * 5.1 劳动节活动开始页面
     *
     */

    public function doMobileWorkDay(){

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
            echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/road38/images/cardhead.png"/>';
            echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
        } else {
            $sys=pdo_fetch("SELECT work_switch FROM ims_work_switch");

            if($sys['work_switch'] == 1){
                include $this->template('member/roadending');
                exit();
            }else if($sys['work_switch'] == 2){
                include $this->template('member/roadwrong');
                exit();
            }
            else {
                global $_W;
                $typea=$_GET['typea'];
                $ustatus='';
                $openid = $_W['openid'];
                $userip=$_SERVER['REMOTE_ADDR'];
                $ident=pdo_fetch("SELECT uid,follow FROM ims_mc_mapping_fans WHERE openid='{$openid}'");
                $info=pdo_fetch("SELECT * FROM ims_work_ownerinfo WHERE openid ='{$openid}'");
                $card=pdo_fetch("SELECT * FROM ims_work_card WHERE openid ='{$openid}'");
                if($ident){
                    if($ident['uid']!=0 && $ident['follow']==0){
                        $ustatus=1;
                    }elseif($ident['uid']!=0 && $ident['follow']==1){
                        $ustatus=1;
                    }elseif($ident['uid']==0 && $ident['follow']==0){
                        $ustatus=2;
                    }else{
                        $ustatus=2;
                    }
                }else{
                        $ustatus=3;
                }

                if(!empty($info) && !empty($card)){
                    $code= $card['code'];
                    include $this->template('member/workopencard');
                }else{
                    include $this->template('member/work51');
                }
            }
        }
    }

    public function Workmember($name,$tel,$idNum,$openid)
    {
        $data=array('name'=>$name,'idNum'=>$idNum,'tel'=>$tel,'openid'=>$openid);
        $resArr=$this->CustRegsiter($data);
        if($resArr['data']['code']==0)
        {
            $resA=$this->getUserInfoFromErp( $resArr['data']['custid']);
            if($resA['data']['code']==0)
            {
               $res=$this->SynLocalInfoFromErp($resA['data']['info'],$openid);
               if($res)
               {
                   $response = array('status' => 0,'errmsg' => "OK");
               }else{
                   $response = array('status' => 6,'errmsg' => "同步信息失败");
               }
            }elseif($resA['data']['code']==11){
                $response = array('status' => 4,'errmsg' =>"注册失败，信息有重复");
            }else{
                $response = array('status' => 5,'errmsg' => "系统繁忙，请稍后重试");
            }
        }elseif($resArr['data']['code']==11){
            $response = array('status' => 4,'errmsg' => "注册失败，信息有重复");
        }else{
            $response = array('status' => 5,'errmsg' => "系统繁忙，请稍后重试");
        }
        return $response;
    }

    /***
     * 5.1  已领卡用户扫码成会员
     */
    public function doMobileAsMember()
    {

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
            echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/road38/images/51top.png"/>';
            echo "<h4 style='display:inline-block'> :-)  请使用微信扫一扫  在微信打开此网页 3Q ^_^ </h4>";
        } else {
            global $_W;
            $openid = $_W['openid'];
            $follow=pdo_fetch("select uid,follow from ims_mc_mapping_fans where openid='{$openid}'");
            if(empty($follow) && $follow['follow']==0){
              echo  "<script> alert('请先关注赛格国际微信公众号')</script>";
            }else{
                if($follow['uid']!=0){
                    echo  "<script>window.location.href='http://wx.cnsaga.com/app/index.php?i=4&c=entry&eid=1&wxref=mp.weixin.qq.com#wechat_redirect';</script>";
                }else{

                    $info = pdo_fetch("select name,idNum,tel from ims_work_ownerinfo where openid='{$openid}'");
                    if (!empty($info)) {
                        $res = $this->Workmember($info['name'], $info['tel'], $info['idNum'], $openid);
                        if ($res['status'] == 0) {
                            include $this->template('member/reg51');
                        } else {

                            include $this->template('member/regerr');
                        }

                    } else {
                        echo "<script>window.location.href='http://wx.cnsaga.com/app/index.php?i=4&c=entry&eid=1&wxref=mp.weixin.qq.com#wechat_redirect';</script>";
                    }
                }
            }

        }


    }
    /***
     *  SAPI 会员注册
     * @param $idNum
     * @return bool
     */
    public function CustRegsiter($data)
    {
        $url=$this->apiHost."?service=Customer.CustRegsiter";
        $res=$this->http_post_attach($url,$data);
        $resArr=json_decode($res,true);
        return $resArr;
    }
    /***
     *      SAPI 查询ERP
     * @param $idNum
     * @param $custId
     * @return bool
     */

    public function getUserInfoFromErp($custId)
    {

        $data=array('custId' =>$custId);
        $url="http://192.168.0.20/?service=Customer.GetCustInfo";
        $res=$this->http_post_attach($url,$data);
        $resArr=json_decode($res,true);
        return $resArr;


    }
    /***
     * 从ERP同步数据到本地数据库
     * @param $memberInfo
     * @param $openid
     * @param $tel
     * @return mixed
     */
    protected function SynLocalInfoFromErp($memberInfo,$openid)
    {

        $name	=$memberInfo['name'];
        $mobile	=$memberInfo['mobile'];
        $idNum	=$memberInfo['idNum'];
        $cardId	=$memberInfo['cardId'];
        $custId	=$memberInfo['custId'];

        $members_data = array(
            'mobile'    => $mobile,
            'uniacid'   => '4',
            'realname'  => $name,
            'idcard'    => $idNum,
            'is51'      =>5,
            'createtime' => time()
        );
        $result = pdo_insert('mc_members', $members_data);                   //存入ims_mc_members        会员表

        if($result) {
            $uid = pdo_insertid();                                           //获取uid
            pdo_begin();
            $card_data = array(
                'uid' => $uid,
                'uniacid' => '4',
                'custid' => $custId,
                'cardsn' => $cardId,
                'createtime' => time()
            );
            $card_result = pdo_insert('mc_card_members', $card_data);        //存入ims_mc_card_members   会员卡表

            $park_data = array(
                'openid' => $openid,
                'uid' => $uid,
                'score' => 1000,
                'mobile' => $mobile,
                'realname' => $name,
                'create_time' => time(),
            );
            $park_result = pdo_insert('park_member', $park_data);             //存入ims_park_member       停车积分

            $fans_result = pdo_update('mc_mapping_fans',
                array('uid'=>$uid),
                array('openid'=>$openid)
            );                                                                //修改粉丝uid

            if($card_result && $park_result && $fans_result)
            {
                pdo_commit();
                return $uid;
            }else{
                pdo_rollback();
                log("此{$openid}对应数据存储失败",'SynLocalInfoFromErp');
            }

        }else{
            return $uid=0;
            log("身份证{$idNum}对应数据存储member表失败",'SynLocalInfoFromErp');
        }

    }

    public function doMobileSendCard()
    {

        global $_W;
        $openid=$_POST['openid'];
        $info=pdo_fetch("select name from ims_work_ownerinfo where openid='".$openid."'");
        $acc = WeAccount::create(4);
        $tempData =  array(
            'first'    => array(
                'value' => "您好！邹龙博，欢迎光临赛格国际购物中心。您有2000积分，可抵用7小时停车费。\n",
                'color' => "#69008C"
            ),
            'keyword1' => array(
                'value' =>'15667104861',
                'color' => '#000000'
            ),
            'keyword2' => array(
                'value' => '陕DQL198',
                'color' => '#000000'
            ),
            'keyword3' => array(
                'value' =>date('Y-m-d H:i:s')."\n",
                'color' => '#000000'
            ),
            'remark'   => array(
                'value' =>"赛格叫你来过节！“陕A车牌以外”个人车主可获赛格国际300元电子购物卡，快把好消息告诉你其他地市的小伙伴们吧，点击查看！如有疑问，询029-86300000",
                'color' => "#69008C"
            ),

        );
        $rss = $acc->sendTplNotice($openid, '3FHdIxzq-r24IBspWkrwAG29RiNRVa4PY2u1XxvWEAk', $tempData, $_W['siteroot'].'app/index.php?i=4&c=entry&do=Resend51&m=member', '#69008C');
    }
    public function doMobileGoSendCard()
    {
        $openid=array('openid'=>$_POST['openid']);
        $token=$this->doMobileGetToken();
        $url="https://api.weixin.qq.com/cgi-bin/message/mass/send?access_token=".$token;
        $data=array(
            "touser"=>$openid,
            "wxcard"=>array("card_id"=>"pUdGzjkDma4wqg8jcmQrct3023qU"),
            "msgtype"=>"wxcard"

        );
        $data=json_encode($data);
        $res=$this->http_post_attach($url,$data);
        echo $res;
    }

    public function doMobileResend51()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (strpos($user_agent, 'MicroMessenger') === false) {
            // 非微信浏览器禁止浏览
            echo '<img style="margin-left:30%;height:80px;width:80px;display:inline-block" src="../addons/member/template/mobile/road38/images/cardhead.png"/>';
            echo "<h4 style='display:inline-block'> :-) 嘿嘿嘿 请在微信打开此网页 3Q ^_^ </h4>";
        } else {
            global $_W;
            $openid=$_W['openid'];
            $user=array(
                'oUdGzjoFvmZZ362yDiQ-K-79iU_M',
                'oUdGzjgrlM3PAznVY3Cjhok1g9YM',
                'oUdGzji8fe1sVhgamAVXgEGsj3Aw',
                'oUdGzjvE634sWnyMqWNCIqStrbU4',
                'oUdGzjpI1prJlJbOLuIxy1clCoec',
                'oUdGzjq1EMHDwdDBJJEcSqBvd_Aw',
                'oUdGzjmfiEKueAiFyxgu-OOIkHAo',
                'oUdGzjhHNTuNm10IhtcUBrOel3yE',
                'oUdGzjs-s8J10-p22jbIDXA6T-Ok',
                'oUdGzjvnbVLoK99uI8Oz2bZXVCuM',
                'oUdGzjrNvvIwA3uOqrGimKr9GwaM',
                'oUdGzjrPfE-ZAdWoE27dpQ6W6A2I',
                'oUdGzjvLHRCHPRG-2z_lesC9t-C0',
                'oUdGzjr7XX7OR4wzyNiEuA0HvQ0k',
                'oUdGzjpNxWmP9VKGAwSjjq28eZKE',
                'oUdGzjkjAbGJNapkXmj_m1gCpZMs',
                'oUdGzjoLOJzREfAqCwLlSn7w63x8');
            if(in_array($openid,$user)){
                $info=pdo_fetch("select name,tel,platenumber,idNum from ims_work_ownerinfo where openid='".$openid."'");
            include $this->template('member/resend51');
            }else{
                echo "<script>alert('请不要非法访问此页面');</script>";
            }

        }
    }
    
    
    public function doMobileworktest()
    {
        include $this->template('member/worktest');
    }
    

}

