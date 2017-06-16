<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
	global $_GPC, $_W;
	$do=$_GPC['do'];
	$pindex = max(1, intval($_GPC['page']));
	$psize = 40;
	$condition = '';
	$condition .= empty($_GPC['cardsn']) ? '' : " AND `cardsn` LIKE '%".trim($_GPC['cardsn'])."%'";
	$condition .= empty($_GPC['uid']) ? '' : " AND `uid` LIKE '%".trim($_GPC['uid'])."%'";
	$condition .= empty($_GPC['realname']) ? '' :" AND `realname` LIKE '%".trim($_GPC['realname'])."%'";
	$condition .= empty($_GPC['cardname'])  ? '':" AND `cardname` LIKE '%".trim($_GPC['cardname'])."%'";

	/*$sql ="SELECT D.uid,realnem,D.createtime,weixin,cardsn,D.status,cardname"
			."(SELECT C.uid,realname,createtime,weixin,C.status,cardname from "
			."(SELECT uniacid,A.uid,realname,createtime,weixin,cardsn,status,card_id from "
			.tablename('mc_members')."AS A "
			."LEFT JOIN "
			.tablename('membership_info')." AS B "
			."ON A.uid=B.uid".") AS C "
			."LEFT JOIN"
			.tablename('mc_create_cards')."AS D "
			."ON C.card_id=D.card_id"
			." AND C.uniacid = '{$_W['uniacid']}' ) AS D"
			."LEFT JOIN"
			.tablename('mc_card_members')."AS E"
			."ON D.uid=E.uid "
			.$condition
			." ORDER BY createtime DESC LIMIT "
			. ($pindex - 1) * $psize . ',' . $psize;*/
	$sql="SELECT E.uid,realname,createtime,E.status,cardname,cardsn,custid from 
			(select C.uniacid,C.uid,realname,C.createtime,C.status,cardsn,card_id,custid from
			 (SELECT uniacid,A.uid,realname,createtime,status,card_id from "
		.tablename('mc_members')." AS A LEFT JOIN "
		.tablename('membership_info')." AS B "
		." ON A.uid=B.uid) AS C "
		."LEFT JOIN"
		.tablename('mc_card_members')." AS D "
		."ON C.uid=D.uid) AS E"
		." LEFT JOIN" 
		.tablename('mc_create_cards')." AS D "
		."ON E.card_id=D.card_id AND E.uniacid = '{$_W['uniacid']}' "
		." WHERE E.uid!='' "
		.$condition
		." ORDER BY createtime DESC LIMIT "
		. ($pindex - 1) * $psize . ',' . $psize;
	$list = pdo_fetchall($sql);
	$cardlist=pdo_fetchall("SELECT cardname FROM ".tablename('mc_create_cards')." WHERE card_status='1'");
	$total = pdo_fetchcolumn("SELECT COUNT(*) from 
			(select C.uniacid,C.uid,realname,C.createtime,C.status,cardsn,card_id,custid from
			 (SELECT uniacid,A.uid,realname,createtime,status,card_id from "
			.tablename('mc_members')." AS A LEFT JOIN "
			.tablename('membership_info')." AS B "
			." ON A.uid=B.uid) AS C "
			."LEFT JOIN"
			.tablename('mc_card_members')." AS D "
			."ON C.uid=D.uid) AS E"
			." LEFT JOIN" 
			.tablename('mc_create_cards')." AS D "
			."ON E.card_id=D.card_id AND E.uniacid = '{$_W['uniacid']}' "
			." WHERE E.uid!='' "
			.$condition);
	$pager = pagination($total, $pindex, $psize);

//解绑，斩断用户与会员的联系,uid为0,mobile与idcard置空
if($do=='del')
{
	if(!empty($_GPC['uid'])) {
			$uid=$_GPC['uid'];						
			//uid 置0
			$sql="UPDATE ".tablename('mc_mapping_fans')." SET uid='0' WHERE  uid='{$uid}'";
			$r=pdo_query($sql);
			//置空mobile
			$sql1="UPDATE ".tablename('mc_members')." SET mobile='' WHERE uid='{$uid}'";
			$s=pdo_query($sql1);
			//置空 idcard
			$sql2="UPDATE ".tablename('mc_members')." SET idcard='' WHERE uid='{$uid}'";
			$q=pdo_query($sql2);
			if($r && $q && $s)
			{
		    	message('解绑成功！','', 'success');
		 
		 	}else{
				message('请重新进行解绑操作！','', 'error');
			}
	}
}
template('mc/wx_member');