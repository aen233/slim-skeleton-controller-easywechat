<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

$info = pdo_fetch('select * from ims_mc_members_greeting where id = :id',array('id'=>$_GPC['id']));

require $this->template('member/createCard');