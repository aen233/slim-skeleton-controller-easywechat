<?php
global $_W,$_GPC;

/**
 *  status [1:新会员,2:实体会员未绑定]
 */

if ($_W['ispost'] && $_W['isajax']) {
    // 获取用户手机号及身份证号
    $mobile = $_GPC['mobile'];
    $idNum  = strtoupper($_GPC['idNumber']);

    // 通过证件号获取会员编号
        //接口测试14 new
        $infoParam=array(
            'idNum'=>$idNum,
        );
        $custInfoArr=$this->apiGetCustInfo($infoParam);


        //if($custInfoArr['data']['code']==11){
        //    echo json_encode(array('status' => 0, 'msg' => '获取会员卡信息失败')); exit();
        //}


        //if ($custInfoArr['data']['info']['mobile'] != $mobile) {
        //    echo json_encode(array('status' => 0, 'msg' => '该证件号已是会员，您输入的手
        //    机号码与原会员手机号码记录不匹配。为了您会员帐户的安全，请携带有效证件至一楼服务台进行办理')); exit();
        //}
        //$idNumTag = true;

        //接口测试15 old
        /*
        $custIdByIdNum = $this->soapLink()->getCustomerCustID(array('idNum'=>$idNum));
        if (!empty($custIdByIdNum)) {
            //接口测试15 old
             $userInfoByIdNum = $this->soapLink()->getCustomerInfo($custIdByIdNum);

            $userInfoByIdNum=$custInfoArr['data']['info'];

            if (empty($userInfoByIdNum)) {
                echo json_encode(array('status' => 0, 'msg' => '获取会员卡信息失败')); exit();
            }

            if ($userInfoByIdNum['mobile'] != $mobile) {
                echo json_encode(array('status' => 0, 'msg' =>
                    '该证件号已是会员，您输入的手机号码与原会员手机号码记录不匹配。
                    为了您会员帐户的安全，请携带有效证件至一楼服务台进行办理'));
                exit();
            }

            $idNumTag = true;
        }
        */

    //接口测试16 new
    $infoByTelParam = array(
        'tel'=>$mobile,
    );

    $custInfoByTelArr=$this->apiGetCustInfo($infoByTelParam);
    //if($custInfoByTelArr['data']['code']==11){
    //    echo json_encode(array('status'=>0, 'msg'=>'获取会员信息失败')); exit();
    //}

    if(!empty($custInfoByTelArr['data']['info'])){
        $mobileTag = true;
        $idNumTag = true;
    }
    //if (strcmp(strtoupper($custInfoByTelArr['data']['info']['idNum']),$idNum) != 0) {
    //    echo json_encode(array('status'=>0, 'msg'=>'该手机号码已经是会员，
    //    您输入的证件号与原会员证件号记录不匹配。为了您会员帐户的安全，请携带有效证件至一楼服务台进行办理')); exit();
    //}
    //$mobileTag = true;

    //接口测试16 old
    // 通过手机号获取会员编号



    /*
    $custIdByMobile = $this->soapLink()->getCustomerCustID(array('tel'=>$mobile));

    if (!empty($custIdByMobile)) {
        // 获取会员信息
        $userInfoByMobile = $this->soapLink()->getCustomerInfo($custIdByMobile);

        if (empty($userInfoByMobile)) {
            echo json_encode(array('status'=>0, 'msg'=>'获取会员信息失败')); exit();
        }

        if (strcmp(strtoupper($userInfoByMobile['idNum']),$idNum) != 0) {
            echo json_encode(array('status'=>0, 'msg'=>'该手机号码已经是会员，
                您输入的证件号与原会员证件号记录不匹配。为了您会员帐户的安全，
                请携带有效证件至一楼服务台进行办理')); exit();
        }

        $mobileTag = true;
    }
    */

    if ($mobileTag === true && $idNumTag === true) {
        echo json_encode(array(
            'status' => 2,
            'mobile' => base64_encode($mobile),
            'idNum' => base64_encode($idNum))
        );

        exit();

    } else {
        //header("location:index.php?i=4c=entry&do=newmembers&m=member");
        echo json_encode(array('status' => 1, 'mobile' => $mobile, 'idNum' => $idNum));  exit();
    }

} else {

    // 打开页面先判断有没有绑定过会员，如果绑定过，跳转至会员中心页
    if ($uid = mc_openid2uid($_W['openid'])) {
        $tag = ($_GPC['source'] == 'ibeacon') ? 1 : 0;
        header("location: ".$_W['siteroot']."app/index.php?i={$_W['uniacid']}&c=entry&eid=1&tag={$tag}");
    }

    // 用户通过输入手机号及身份证号后提交判断为新会员或老会员并跳转至相应页面
    include $this->template('userRegist/index');
}