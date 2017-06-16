<?php
global $_W,$_GPC;

if ($_W['ispost'] && $_W['isajax']) {
    $uid = mc_openid2uid($_W['openid']);
    $platenumber = $_GPC['platenumber'];

    /**
     * 判断是否已绑定车牌
     */
    $platenumbers = pdo_fetch("select `platenumber`,`platenumber2`,`platenumber3` from `ims_mc_members` where `platenumber` = '{$platenumber}' or `platenumber2` = '{$platenumber}' or `platenumber3` = '{$platenumber}'");
    if (!empty($platenumbers['platenumber']) || !empty($platenumbers['platenumber2']) || !empty($platenumbers['platenumber3'])) {
        echo json_encode(array('status' => 0, 'msg' => '车牌号 ' . $platenumber . ' 已经绑定过，不能重复绑定'));
        exit();
    }

    //接口测试inc 1 new
    $parkParam = array(
        'plateNumber' => $platenumbers,
    );
    $parkInfoArr = $this->apiGetPlateNumber($parkParam);
    $prakInfoData = $parkInfoArr['data']['data'];

    //如果data返回值为空
    if (empty($prakInfoData)) {
        //调用添加车牌接口
        $host=$this->apiHost;
        $addParkParam = array(
            'plateNumber' => $platenumbers,
        );

        $addParkInfoArr=$this->apiAddPlateNumber($addParkParam);
       

        if ($addParkInfoArr['data'] != '') {
            /**
             * 更新会员车牌
             */
            $result = pdo_update('mc_members', array('platenumber' => $platenumber, 'isOn' => 1), array('uid' => $uid));
            if ($result !== false) {
                /**
                 * 首次绑定送1000积分
                 */
                $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid", array('uid' => $uid));
                if ($count == 0) {
                    $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
                    $id = pdo_insertid();
                }
                if (!empty($id)) {
                    $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid", array('uid' => $uid));
                    if ($parkCount == 0) {
                        $memberInfo = mc_fetch($uid, array('mobile', 'realname'));
                        pdo_insert('park_member', array('openid' => $_W['openid'], 'uid' => $uid, 'score' => 1000, 'mobile' => $memberInfo['mobile'], 'realname' => $memberInfo['realname'], 'create_time' => TIMESTAMP));
                    } else {
                        pdo_query("UPDATE `ims_park_member` SET `score` = score + 1000 WHERE `uid` = '{$uid}'");
                    }
                }
                echo json_encode(array('status' => 1, 'msg' => '绑定车牌成功'));
            } else {
                echo json_encode(array('status' => 0, 'msg' => '绑定车牌失败'));
            }
        } else {
            include $this->template('userRegist/bindPlateNum');
        }
    }
}


//    //接口测试inc 1 old
//    /**
//     * 添加停车场系统白名单
//     */
//
//    $client      = new SoapClient('http://113.140.80.194:8088/WebServiceForWeixin.asmx?wsdl');
//    $getRequest1 = $client->__soapCall('GetMemberPlateNumberInfo', array(array('plateNumber' => $platenumber)));
//
//    if (empty($getRequest1->GetMemberPlateNumberInfoResult)) {
//
//
//        $addRequest = $client->__soapCall('AddMemberPlateNumber', array(array('plateNumber' => $platenumber)));
//
//
//        if ($addRequest->AddMemberPlateNumberResult) {
//            /**
//             * 更新会员车牌
//             */
//            $result = pdo_update('mc_members', array('platenumber' => $platenumber, 'isOn' => 1), array('uid' => $uid));
//
//            if ($result !== false) {
//                /**
//                 * 首次绑定送1000积分
//                 */
//                $count = pdo_fetchcolumn("select count(*) from ims_park_platenumber_log where uid = :uid", array('uid' => $uid));
//                if ($count == 0) {
//                    $result = pdo_insert('park_platenumber_log', array('uid' => $uid, 'create_time' => time()));
//                    $id = pdo_insertid();
//                }
//
//                if (!empty($id)) {
//                    $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid", array('uid' => $uid));
//                    if ($parkCount == 0) {
//                        $memberInfo = mc_fetch($uid, array('mobile', 'realname'));
//                        pdo_insert('park_member', array('openid' => $_W['openid'], 'uid' => $uid, 'score' => 1000, 'mobile' => $memberInfo['mobile'], 'realname' => $memberInfo['realname'], 'create_time' => TIMESTAMP));
//                    } else {
//                        pdo_query("UPDATE `ims_park_member` SET `score` = score + 1000 WHERE `uid` = '{$uid}'");
//                    }
//                }
//
//                echo json_encode(array('status' => 1, 'msg' => '绑定车牌成功'));
//            } else {
//                echo json_encode(array('status' => 0, 'msg' => '绑定车牌失败'));
//            }
//        }
//    }
//} else {
//    include $this->template('userRegist/bindPlateNum');
//}


