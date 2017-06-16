<?php

ini_set("display_errors", "On");
error_reporting(0);

/**
 * Class EloquenceModuleSite
 */
//load()->classs('CollectionProvider');
//load()->classs('ServiceProvider');
load()->classs('ErpServiceProvider');
//load()->classs('CardApi');

load()->func('communication');


/**
 * 加载数据
 * Class EloquenceModuleSite
 */
class EloquenceModuleSite extends WeModuleSite
{



    /**
     * @var ErpServiceProvider
     */
    public $erpServiceProvider;

    /**
     * 链接 token 数据字符串
     * @return string
     */
    protected function linkToken()
    {
        return "?access_token=" . file_get_contents(
            "http://wx.cnsaga.com/app/index.php?i=4&c=entry&do=getToken&m=member"
        );
    }

    /**
     * 提供数据容器操作组件
     * EloquenceModuleSite constructor.
     */
    public function __construct()
    {
        //$this->container          = new CollectionProvider();
        $this->erpServiceProvider = new ErpServiceProvider();
    }

    /*
     * HTTP 数据组件
     */
    public function http_attach_post($url, $param)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }


    /**
     *  一键跳转型激活后 code 解码
     */
    protected function decryptCode()
    {
        $request_url = $this->erpServiceProvider['decrypt'] . $this->linkToken();
        $requestData = array('encrypt_code' => $_GET['encrypt_code']);
        $sendData = json_encode($requestData);

        $decryptData = $this->http_attach_post($request_url, $sendData);

        $retMsg = json_decode($decryptData, true);
        if (0 == $retMsg['errcode']) {
            return $retMsg['code'];
        } else {
            return false;
        }
    }



    // 使用ticket 获取 微信字段信息
    protected function redirectActive()
    {
        //1 通过get activatie

        $request_url = $this->erpServiceProvider['activatetempinfo'] . $this->linkToken();
        $requestData = array("activate_ticket" => $_GET['activate_ticket']);
        $afterCommitInfo = $this->http_attach_post($request_url, json_encode($requestData));

       // exit($afterCommitInfo);
        $ret_msg = json_decode($afterCommitInfo, true);
        /**
         *
         */
        if (0 == $ret_msg['errcode']) {
            $vectorInScMsg = $ret_msg['info']['common_field_list'];         // 指定信息字段
            $names = array();
            $val = array();

            array_walk_recursive($vectorInScMsg, function($item, $key) use (&$names, &$val){
                if('U' == mb_substr($item,0,1)){
                    $names[] = $item;
                }else{
                    $val[] = $item;
                }
            });

            $resource = array_combine($names, $val);
            // 自定义信息字段
            return $resource;
        }
    }


    /**
     *  一键跳转型激活会员领卡入口
     */
    public function doMobileRedirectActive()
    {
        global $_W;
        $openid = $_W['openid'];                                // 获取解码的Code
        $code   = $this->decryptCode();                           // 获取提交的数据信息
        $afterCommitInfo = $this->redirectActive();             /* var_dump($afterCommitInfo);exit;*/
        $idNum  =   array('idNum' => $afterCommitInfo['USER_FORM_INFO_FLAG_IDCARD']);
        $tel    =   array('tel' => $afterCommitInfo['USER_FORM_INFO_FLAG_MOBILE']);
        $name   =   array('name' => $afterCommitInfo['USER_FORM_INFO_FLAG_NAME']);

        $existCustomerInfo = $this->getUserInfoFromErp($idNum['idNum'],'');    //SAPI 接口查询ERP
        $localInfo = $this->getUserInfoFromLocal($idNum['idNum']);             // 本地数据信息查询
        if (is_array($existCustomerInfo)) {
            if (!$localInfo) {

                $uid=$this->SynLocalInfoFromErp($existCustomerInfo,$openid,$tel['tel']);    //ERP数据同步到本地数据库
            }else{
                $uid=$localInfo['uid'];
            }
            $old='110';
            $member	=	$existCustomerInfo;
           	$custId	=	$existCustomerInfo['custId'];	            //老会员查购物积分专用
           
        } else {
            $old='120';
            $member=array();
            $member['idNum']=$idNum['idNum'];
            $member['name']=$name['name'];
            $cardId='';
            $uid='';
            $custId='';
        }
         include $this->template('list/eloquence');
        
    }


    /***
     *  提交会员卡信息，并激活卡
     */
    public function doMobileInitialCard()
    {
    		global $_GPC;
            $code 	= $_GPC['code'];
            $idNum  = $_GPC['idNum'];
            $tel 	= $_GPC['mobile'];
            $name   = $_GPC['name'];
            $openid	= $_GPC['openid'];
            $custId	= $_GPC['custId'];			                //老会员        新用户无			老会员查购物积分专用
            $cardId = $_GPC['cardId'];                          //老会员        新用户无
            $uid 	= $_GPC['uid'];                             //老会员 	新用户无

            //再次调用ERP接口确认，所提交信息用户的身份,调用ERP接口，对新用户进行注册
            $existCustomerInfo = $this->getUserInfoFromErp($idNum,'');
            if(!is_array($existCustomerInfo))                    //若为空，则判定为新用户，先注册，再激活
            {
            	$custId = $this->CustRegsiter($idNum,$tel,$name);
                if($custId)
                {
                    $existCustomerInfo = $this->getUserInfoFromErp('',$custId);         //获取ERP新注册会员信息
                    $cardId=$existCustomerInfo['cardId'];
                    $uid=$this->SynLocalInfoFromErp($existCustomerInfo,$openid,$existCustomerInfo['mobile']); //同步到本地
                    if(!$uid)
                    {
                        include $this->template('list/fail');
                        log("此{$openid}对应数据存储失败",'SynLocalInfoFromErp');
                    }


                }else{
                    include $this->template('list/reg_fail');
                    logs("注册失败".$openid.' tel='.$tel,'regfail');
                    exit;
                }
            }
            $score=$this->GetCustScore($custId);	            //购物积分
            $res = $this->ActivateCard($cardId, $code,$score);  //调用微信激活接口 传入初始信息

            if($res == true){
                pdo_begin();
                $ship_data=array(                                //$ship_data 存入新表ims_membership_info
                    'uid'		 =>$uid,
                    'cardsn'	 =>$cardId,
                    'active_time'=>time(),
                    'status'	 =>'1'
                );
                $result = pdo_update('membership_info', $ship_data,array('openid'=>$openid));
				if($result) {
				    pdo_commit();
                    include $this->template('list/tips');
                }
                else{
				    pdo_rollback();
                    logs('uid='.$uid."激活信息存储失败 openid=".$openid.' cardsn='.$cardId,'activefail');
                }
			}else{
				include $this->template('list/errtips');
                logs("流程激活失败openid=".$openid.' tel='.$tel,'errtips');
			}
        
    }

    /**
     * 激活会员卡接口
     * @param $memberShip
     * @param $code
     */
    protected function ActivateCard($memberShip, $code, $score)
    {	
        	$con = substr($memberShip, 0, 1);
        	$vip = ($con == '8') ? 'VVVIP' : 'VIP';
        	$bgpic_url=($vip=='VVVIP')?'http://mmbiz.qpic.cn/mmbiz_png/6Qs5bXJZ38LxrnPkdZvp4JMNjB238LeYHj8dd8qTDxH0ww1N6R2Rb9scTZWaVAEJD442Uc7FhHLelxoHyXaQZg/0?wx_fmt=jpeg':'http://mmbiz.qpic.cn/mmbiz_png/6Qs5bXJZ38LxrnPkdZvp4JMNjB238LeYeyWkia5PKGpxE9whSqeJtxqZTDyvq5rY4WXtuWdd1qOdChQxevibpMqw/0?wx_fmt=jpeg';
        	$sendInfo = array(
            "init_bonus"=>$score,
            "membership_number"=>$memberShip,
            "code"=>$code,
            "background_pic_url"=>$bgpic_url,
            "init_custom_field_value1"=>$vip
        	);
        	$sendData = json_encode($sendInfo);
	        $request_url = $this->erpServiceProvider['activate'] . $this->linkToken();
	        $response = $this->http_attach_post($request_url, $sendData);
	
	        $reMsg = json_decode($response, true);
	        if (0 == $reMsg['errcode']) {
	            return true;
	        }
	        return false;
	    
    }


    /***  查询本地数据库
     * @param $idcard
     * @return bool
     */
    protected function getUserInfoFromLocal($idcard)
    {
        $sql = "SELECT uid FROM " . tablename('mc_members') . " WHERE  idcard='{$idcard}'";
        $data = pdo_fetchall($sql);
        return empty($data[0]) ? false : $data[0];
    }


    /***
     *  SAPI 会员注册
     * @param $idNum
     * @return bool
     */
    public function CustRegsiter($idNum,$tel,$name)
    {
        $data=array(
            'idNum' =>$idNum,
            'tel'   =>$tel,
            'name'  =>$name
        );
        $url="http://192.168.0.20/?service=Customer.CustRegsiter";
        $res=$this->http_attach_post($url,$data);
        $resArr=json_decode($res,true);
        return empty($resArr['data']['custid']) ? false : $resArr['data']['custid'];
    }

    /***
     *      SAPI 查询ERP
     * @param $idNum
     * @param $custId
     * @return bool
     */

    public function getUserInfoFromErp($idNum,$custId)
    {
        $data=array();
        if($idNum!='')
        {
            $data=array('idNum' =>$idNum);
        }
        if($custId!='')
        {
            $data=array('custId' =>$custId);
        }
        $url="http://192.168.0.20/?service=Customer.GetCustInfo";
        $res=$this->http_attach_post($url,$data);
        $resArr=json_decode($res,true);
        return empty($resArr['data']['info']) ? false : $resArr['data']['info'];


    }

    /**
     * SAPI获取积分
     * @param $custId
     * @return int
     */
    public function GetCustScore($custId)
    {
        $data=array('custId'=>$custId);
        $url="http://192.168.0.20/?service=Customer.GetCustScore";
        $res=$this->http_attach_post($url,$data);
        $resArr=json_decode($res,true);
        return  empty($resArr['data']['data']['scoreList']) ? 0 : $resArr['data']['data']['scoreList'];
    }

    /***
     * 从ERP同步数据到本地数据库
     * @param $memberInfo
     * @param $openid
     * @param $tel
     * @return mixed
     */
    protected function SynLocalInfoFromErp($memberInfo,$openid,$tel)
    {
        
		$name	=$memberInfo['name'];
		$mobile	=$tel;
		$idNum	=$memberInfo['idNum'];
		$cardId	=$memberInfo['cardId'];
		$custId	=$memberInfo['custId'];
        
            $members_data = array(
                'mobile' => $mobile,
                'uniacid' => '4',
                'realname' => $name,
                'idcard' => $idNum,
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

                include $this->template('list/fail');
                log("身份证{$idNum}对应数据存储member表失败",'SynLocalInfoFromErp');
                exit();
            }

	}

}
