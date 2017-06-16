<?php

//require_once 'ffansaga.inc.php';

global $_W,$_GPC;


if ($_W['ispost'] && $_W['isajax'] ) {

    // 获取用户提交信息
    $mobile      = $_GPC['mobile'];
    $idNum       = strtoupper($_GPC['idNum']);
    $name        = $_GPC['name'];
    $gender      = $_GPC['gender'];
    $isValued    = $_GPC['isValued'];

    // 判断手机号、证件号、姓名是否为空
    if (empty($mobile) || empty($idNum) || empty($name)) {
        echo json_encode(array(
            'status' => 0,
            'msg' => '您好，会员信息填写不完整，请您补全信息，完成注册。'
        ));

        exit();
    }

    // 判断用户是否已经注册
    $uid_fans   = mc_openid2uid($_W['openid']);
    $uid_member = pdo_fetchcolumn("select uid from ".tablename('mc_members').
        " where mobile = :mobile or idcard = :idNum",
        array('mobile'=>$mobile,'idNum'=>$idNum));
    if (!empty($uid_member) || !empty($uid_fans)) {
        echo json_encode(array('status' => 0, 'msg' => '您好，您已绑定过会员，已经可以享受会员服务。')); 
		exit();
    }

    // 获取粉信息
    $fansInfo = mc_fansinfo($_W['openid']);
    if (empty($fansInfo)) {
        echo json_encode(array('status'=>0, 'msg'=>'获取粉丝信息失败'));exit();
    }




    // 在 ERP 系统中注册会员基本信息

    $custInfo = $this->apiCustRegsiter(array(
        'name'=>$name,
        'idNum'=>$idNum,
        'tel'=>$mobile,
        'idType'=>'H'
        )
    );

    $custId = $custInfo['data'];
   // $custId = $this->soapLink()->saveCustomer(array('name'=>$name,'idNum'=>$idNum,'tel'=>$mobile,'idType'=>'H'));

    //var_dump($custInfo);

    if (!empty($custId['custid'])) {

        // 获取会员卡信息
        //$custinfo = $this->soapLink()->getCustomerInfo($custId['custId']);

        $infoParam=array(
            'custId'=>$custId['custid'],
        );
        $custinfoArr = $this->apiGetCustInfo($infoParam);
        $custinfo = $custinfoArr['data']['info'];


        //var_dump($custinfo);
        //exit();


        if($custinfoArr['data']['code'] == 0){
            try {
                // 开启事务
                pdo_begin();

                $memberData = array(
                    'mobile'      => $mobile,
                    'uniacid'     => $_W['uniacid'],
                    'realname'    => $name,
                    'gender'      => $gender,
                    'avatar'      => '',
                    'nickname'    => $fansInfo['nickname'],
                    'createtime'  => TIMESTAMP,
                    'idcard'      => $idNum,
                    'isValued'    => $isValued
                );

                // 写入会员信息
                $memres = pdo_insert('mc_members', $memberData);
                $uid    = pdo_insertid();
                if (empty($uid)) {
                    throw new Exception('会员表【mc_members】写入数据失败 数据为： '.json_encode($memberData));
                }

                // 更新粉丝UID
                $updateFansInfo =  pdo_update('mc_mapping_fans',
                    array('uid' => $uid),
                    array('openid'  => $_W['openid'],
                        'uniacid' => $_W['uniacid'])
                );

                if ($updateFansInfo === false) {
                    throw new Exception('更新粉丝UID失败 UID：'.$uid.' Openid: '.$_W['openid']);
                }


                // 添加会员卡信息
                $cardData = array(
                    'uniacid'    => $_W['uniacid'],
                    'uid'        => $uid,
                    'send'       => 0,
                    'custid'     => $custinfo['custId'],
                    'cardsn'     => $custinfo['cardId'],
                    'status'     => 1,
                    'createtime' => TIMESTAMP
                );
                $addCardInfo = pdo_insert('mc_card_members',$cardData);

                if (empty($addCardInfo)) {
                    throw new Exception('会员卡表【mc_card_members】写入数据失败 数据为： '.json_encode($cardData));
                }

                // 飞凡会员信息准备
                /*$ffanSyncInfo = array();

                $ffanSyncInfo['appid']      = 'saga';
                $ffanSyncInfo['uid']        =  $cardData['custid'];
                $ffanSyncInfo['mobile']     =  $mobile;
                $ffanSyncInfo['bizId']      =  '10014';

                $ffanSyncInfo['cardNo']     =  $cardData['cardsn'];
                $ffanSyncInfo['channel']    =  0;
                //调用方细分渠道
                $ffanSyncInfo['realName']   =  $memberData['realname'];
                $ffanSyncInfo['idcardNo']   =  $idNum;
                $ffanSyncInfo['idcardType'] =  1;
                $ffanSyncInfo['nickName']   =  $memberData['nickname'];

                $itemOpt = new FFantosagasync();

                $resultInfo = $itemOpt->postSagaCustomerInfo($ffanSyncInfo, FFantosagasync::NEW_MEMBER_INTER);

                $retInfo = json_decode($resultInfo, true);

                if ($retInfo['status'] == '200') {
                    $message = '成功';
                }else if($retInfo['status'] == '1109'){
                    $message = "手机号已被其他用户注册";
                }else if($retInfo['status'] == '1104'){
                    $message = "手机验证码错误";
                }else{
                    $message = $retInfo['message'];
                }

                logs('飞凡注册会员: '.$message.' 姓名：'.$name.' 手机号： '.$mobile.' 证件号：'.$idNum.' 日期：'.date('Y-m-d H:i:s'),'saveCustomer');
                */

                // 首次绑定送1000积分
                $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid",
                    array('uid'=>$uid));
                if ($count == 0) {
                    $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
                    $id     = pdo_insertid();
                }

                if (!empty($id)) {
                    $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid",
                        array('uid'=>$uid));

	                // 对于微信专场活动送2000积分
                    // $score = ($_GPC['source'] == 1) ? 2000 : 1000;
	                $score =  1000;
	                /* if ($score == 2000) {
			            pdo_insert('park_ibeacon_log', array('uid' => $uid, 'create_time' => time()));
		            }*/

                    if ($parkCount == 0){
                        $memberInfo = mc_fetch($uid,array('mobile','realname'));

                        pdo_insert('park_member',array(
                            'openid'=>$_W['openid'],
                            'uid'=>$uid,
                            'score'=>$score,
                            'mobile'=>$memberInfo['mobile'],
                            'realname'=>$memberInfo['realname'],
                            'create_time' => TIMESTAMP
                        ));
                    } else {
                        pdo_query("UPDATE `ims_park_member` SET `score` = score + $score WHERE `uid` = '{$uid}'");
                    }
                }

                // 补充优惠券信息
                //$password = pdo_query("update card_card_card set openid = :openid where
                // tel = '{$mobile}'and r_way = 3",array('openid'=>$_W['openid']));

                pdo_commit();


				$return_msg = array(
				    'status' => 1,
                    'msg'    => '恭喜您！注册成功！尽情享受会员权益吧！',
                    'score'  => $score
                );


                /**
				if (!empty($uid)) {

					//$scene = pdo_fetchcolumn("select scene from " . tablename('mc_mapping_fans') . "
				where uid = :uid",array('uid'=>$uid));
					$mapfans = pdo_fetch("select scene,followtime from " .
                        tablename('mc_mapping_fans') . " where uid = :uid",array('uid'=>$uid));
					//2227 出礼品
					//时间判断 关注后3分钟算失效
					//有效时间
					$endtime = $mapfans['followtime']+180;
					if(isset($mapfans['scene']) && in_array($mapfans['scene'],
				       array('2227')) && $mapfans['followtime'] < $endtime){
						//$result = funQuzhiphp('gh_698ee33fe1d9',$_W['openid']);
						$machine_code = '1012';
						//如果有多个机台
						if($mapfans['scene'] == '2227'){
							$machine_code = '1012';
						}
						$result = dxs_out_goods('gh_698ee33fe1d9', $_W['openid'], $uid, $machine_code);
						if(isset($result['result']) && $result['result'] == 'success'){
							//var_dump($result);
							//增加提示消息
							$return_msg = array('status'=>1,'msg'=>'恭喜您！注册成功！
				尽情享受会员权益吧！纸巾君在此免费送您一包纸巾！','score'=>$score);

						}

					}
				}
                */
				echo json_encode($return_msg);
				exit();


            } catch (Exception $e) {
                logs($e->getMessage(),'newMember');
                pdo_rollback();
                echo json_encode(array('status'=>0,'msg'=>'注册会员失败')); exit();
            }

        } else {
            echo json_encode(array('status'=>0,'msg'=>'会员卡号不存在'));
        }
    } else {
        logs('ERP注册会员失败: '.$custId['error']
            .' 姓名：'.$name.' 手机号： '.$mobile.' 证件号：'.$idNum.' 日期：'.
            date('Y-m-d H:i:s'),'saveCustomer');

        switch ($custId['error']) {
            case '检查手机号已经存在':
                $msg = '手机号已注册过';
                break;
            case '检查身份证已经存在':
                $msg = '身份证号已注册过';
                break;
            default:
                $msg = '注册会员失败';
        }

        echo json_encode(array('status'=>0,'msg'=>$msg)); exit();
    }
} else {

    include $this->template('userRegist/newMembers');
}