<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

if ($_W['ispost'] && $_W['isajax']) {
    $uid = mc_openid2uid($_GPC['openid']);
    if ($_GPC['isFollow'] && !empty($uid)) {

        $shareId = pdo_fetchcolumn('select id from ims_share where openid = :openid', array('openid'=>$_GPC['openid']));
        if (empty($shareId)) {
            $memberInfo = mc_fetch($uid,array('mobile','realname'));
            $parkCount = pdo_fetchcolumn("select count(*) from ims_park_member where uid = :uid",array('uid'=>$uid));
            try {
                pdo_begin();
                $shareLog = pdo_insert('share',array('openid'=>$_GPC['openid'],'create_time'=>TIMESTAMP));

                if (!$shareLog) {
                    throw new Exception('添加分享记录失败: openid('.$_GPC['openid'].') uid('.$uid.')');
                }


                if ($parkCount == 0){
                    $park_id = pdo_insert('park_member',array('openid'=>$_W['openid'],'uid'=>$uid,'score'=>1000,'mobile'=>$memberInfo['mobile'],'realname'=>$memberInfo['realname'],'create_time' => TIMESTAMP));
                } else {
                    $park_id = pdo_query("UPDATE `ims_park_member` SET `score` = score + 1000 WHERE `uid` = '{$uid}'");
                }

                if ($park_id === false) {
                    throw new Exception('更新会员积分失败: openid('.$_GPC['openid'].') uid('.$uid.')');
                }

                pdo_commit();
                echo '恭喜您已经获取 1000 停车专用积分。请到【我的】-【会员卡】中查看';exit();
            } catch (Exception $e) {
                logs($e->getMessage(),'Share');
                pdo_rollback();
            }

        }

    } else {
        $shareId = pdo_fetchcolumn('select id from ims_share where openid = :openid', array('openid'=>$_GPC['openid']));
        if (empty($shareId)) {
            try {
                pdo_begin();
                $shareLog = pdo_insert('share',array('openid'=>$_GPC['openid'],'create_time'=>TIMESTAMP));
                if (!$shareLog) {
                    throw new Exception('添加分享记录失败: openid('.$_GPC['openid'].')');
                }
                pdo_commit();
                echo '恭喜您已经获取 1000 停车专用积分。请关注赛格国际购物中心微信公众号后，到【我的】-【会员卡】中查看';exit();

            } catch (Exception $e) {
                logs($e->getMessage(),'Share');
                pdo_rollback();
            }
        }
    }


} else {
    if ($_GPC['id']) {
        $info = pdo_fetch("select title,type from ims_gongyi where id = :id",array('id'=>$_GPC['id']));
        if ($info['type'] == 1) {
            $diannao = $info['title'];
            $msg = $diannao.' 已添加了爱心 现在开始传递 还可享免费停车';
        } else {
            $gouwu = $info['title'];
            $msg = $gouwu.' 已添加了爱心 现在开始传递 还可享免费停车';
        }
    } else {
        $msg = '您已添加了爱心 现在开始传递 还可享免费停车';
    }
    $diannaosh = pdo_fetchall('select title,type from ims_gongyi where type = :type',array('type'=>1));
    $gouwush  =  pdo_fetchall('select title,type from ims_gongyi where type = :type',array('type'=>2));

    $num = mt_rand(1,3);
    pdo_query("update `ims_gongyi_count` set `num` = num + {$num} where `id` = 1");
    $pv = pdo_fetchcolumn('select num from ims_gongyi_count where id = :id',array('id'=>1));
    $pv = number_format($pv);
    include $this->template('member/aixin');
}