<?php
global $_W,$_GPC;

/**
 *  status [1:新会员,2:实体会员未绑定,3:实体会员并绑定微信]
 */

if ($_W['ispost'] && $_W['isajax']) {

    $mobile = $_GPC['mobile'];
    $idNum = $_GPC['idNumber'];

    //接口测试2 inc old
        //$custId = $this->soapLink()->getCustomerCustID(array('idNum'=>$idNum));
    //接口测试2 inc new
    $host=$this->apiHost;
    $infoParam=array(
        'idNum'=>$idNum,
    );
    $custInfoArr=$this->apiGetCustInfo($infoParam);
    $custId=$custInfoArr['data']['info']['custId'];

    if (!empty($custId)) {   // 已申请实体会员
        echo json_encode(array('status'=>2,'mobile'=>$mobile,'idNum'=>$idNum));
    } else {    // 新会员
        echo json_encode(array('status'=>1,'mobile'=>$mobile,'idNum'=>$idNum));
    }

}