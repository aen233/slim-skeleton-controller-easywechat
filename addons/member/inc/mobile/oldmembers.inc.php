<?php

//require_once 'ffansaga.inc.php';


global $_W,$_GPC;

if ($_W['ispost'] && $_W['isajax']) {

    // 使用mysql缓存验证码
    load()->func('cache.mysql');

    $user = $_GPC['user'];
    $code = $_GPC['code'];

    if (empty($user['mobile']) || empty($code) || empty($user['idNum'])) {
        echo json_encode(array(
            'status' => 0,
            'msg' => '您好，会员信息填写不完整，请您补全信息，完成绑定。'
        ));

        exit();
    }

    // 判断是否已经注册
    $uid_fans   = mc_openid2uid($_W['openid']);
    $sql        = "select uid from " . tablename('mc_members') . " where mobile = :mobile or idcard = :idNum";
    $uid_member = pdo_fetchcolumn($sql, array('mobile' => $user['mobile'], 'idNum' => $user['idNum']));
    if (!empty($uid_member) || !empty($uid_fans)) {
        echo json_encode(array('status' => 0, 'msg' => '您好，您已绑定过会员，已经可以享受会员服务。')); 
		exit();
    }

    // 读取验证码
    $codeinfo = cache_load($user['mobile']);
    if($codeinfo['code'] != $code || empty($codeinfo) || $codeinfo['create_time'] < TIMESTAMP){
        echo json_encode(
            array(
                'status'=>0,
                'msg'=>'您好，您的验证码不可使用，请点击“获取验证码”按钮重新接收。'
            )
        );
        exit();
    }


    // 获取粉信息
    $fansInfo = mc_fansinfo($_W['openid']);
    if (empty($fansInfo)) {

        // W T F -_- !!
        //echo json_encode(array('status'=>0, 'msg'=>'获取粉丝信息失败'));exit();

        echo json_encode(array(
            'status'=>0,
            'msg'=>'你还未关注赛格公众号')
        );

        exit();
    }

    try {
        // 开启事务
        pdo_begin();

        $memberData = array(
            'mobile'      => $user['mobile'],
            'uniacid'     => $_W['uniacid'],
            'realname'    => $user['name'],
            'avatar'      => '',
            'nickname'    => $fansInfo['nickname'],
            'createtime'  => TIMESTAMP,
            'idcard'      => $user['idNum'],
            'isValued'    => $_GPC['isValued']
        );

        // 写入会员信息
        $memres = pdo_insert('mc_members', $memberData);
        $uid    = pdo_insertid();
        if (empty($uid)) {
            throw new Exception('会员表【mc_members】写入数据失败 数据为： '.json_encode($memberData));
        }

        // 更新粉丝UID
        $updateFansInfo =  pdo_update('mc_mapping_fans', array('uid' => $uid),
            array('openid'  => $_W['openid'],'uniacid' => $_W['uniacid']));
        if ($updateFansInfo === false) {
            throw new Exception('更新粉丝UID失败 UID：'.$uid.' Openid: '.$_W['openid']);
        }

        // 添加会员卡信息
        $cardData = array(
            'uniacid'    => $_W['uniacid'],
            'uid'        => $uid,
            'send'       => 0,
            'custid'     => $user['custId'],
            'cardsn'     => $user['cardId'],
            'status'     => 1,
            'createtime' => TIMESTAMP
        );
        $addCardInfo = pdo_insert('mc_card_members',$cardData);

        if (empty($addCardInfo)) {
            throw new Exception('会员卡表【mc_card_members】写入数据失败 数据为： '.json_encode($cardData));
        }

        // 首次绑定送1000积分
        $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid",
            array('uid'=>$uid));
        if ($count == 0) {
            $result = pdo_insert('park_platenumber_log',
                array(
                    'uid' => $uid,
                    'create_time' => time()
                )
            );

            $id     = pdo_insertid();
        }

        
        
        if (!empty($id)) {
            $parkCount = pdo_fetchcolumn("select count(*) from 
                ims_park_member where uid = :uid",array('uid'=>$uid));

	        $score =  1000;

            if ($parkCount == 0){
                $memberInfo = mc_fetch($uid,array('mobile','realname'));

                pdo_insert('park_member',array(
                    'openid'=>$_W['openid'],
                    'uid'=>$uid,
                    'score'=>$score,
                    'mobile'=>$memberInfo['mobile'],
                    'realname'=>$memberInfo['realname'],
                    'create_time' => TIMESTAMP)
                );

            } else {
                pdo_query("UPDATE `ims_park_member` SET `score` = score + $score WHERE `uid` = '{$uid}'");
            }
        }

        // 补充优惠券信息
        //$password = pdo_query("update card_card_card set openid = :openid where tel =
        // '{$user['mobile']}'and r_way = 3",array('openid'=>$_W['openid']));

        pdo_commit();
		
		$return_msg = array('status'=>1,
            'msg'=>'恭喜您！注册成功！尽情享受会员权益吧！',
            'score'=>$score);

		echo json_encode($return_msg);

    } catch (Exception $e) {
        logs($e->getMessage(),'oldMember');
        pdo_rollback();
        echo json_encode(array('status'=>0,'msg'=>'注册会员失败')); exit();
    }

} else {
    $idNum = base64_decode($_GPC['idNum']);

    //var_dump($idNum);

    //接口测试11 inc old
    // $custId = $this->soapLink()->getCustomerCustID(array('idNum'=>$idNum));
    // $userinfo = $this->soapLink()->getCustomerInfo($custId);
    //接口测试11 inc new
    $infoParam=array(
        'idNum'=>$idNum,
        //'idNum'=>'610404199407290014',
    );
    $custInfoArr=$this->apiGetCustInfo($infoParam);
    $custId= $custInfoArr['data']['info']['custId'];
    $userinfo= $custInfoArr['data']['info'];


    //var_dump($custInfoArr);

    //var_dump($custId);
    //var_dump($userinfo);
    //exit();

    include $this->template('userRegist/oldMembers');
}