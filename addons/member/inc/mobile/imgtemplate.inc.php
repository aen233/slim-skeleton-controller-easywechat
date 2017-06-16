<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

$info = pdo_fetch('select * from ims_mc_members_greeting where id = :id',array('id'=>$_GPC['id']));

if ($_W['ispost'] && $_W['isajax']) {
    $upInfo = pdo_update('mc_members_greeting',array('template_id'=>$_GPC['tempId'],'remark'=>$_GPC['remark']),array('id'=>$_GPC['id']));
    if ($upInfo !== false) {
        echo json_encode(array('status'=>1,'id'=>$_GPC['id']));
    } else {
        echo json_encode(array('status'=>0,'msg'=> '更新信息失败'));
    }
} else {
    require $this->template('member/imgTemplate');
}