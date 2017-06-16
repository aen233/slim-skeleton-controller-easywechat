<?php
defined('IN_IA') or exit('Access Denied');
global $_W,$_GPC;

$time = date('Y-m-d');
if($_GPC['card_id'] == 221){
	include $this->template('member/gouwu_dior');
}else{
	include $this->template('member/gouwu');
}