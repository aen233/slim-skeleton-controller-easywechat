<?php
	defined('IN_IA') or exit('Access Denied');
	global $_GPC, $_W;
	load()->func('tpl');
	$do=$_GPC['do'];
	$pindex = max(1, intval($_GPC['page']));
	$psize = 40;
	$condition = '';
	$condition .= empty($_GPC['cardsn']) ? '' : " AND `cardsn` LIKE '%".trim($_GPC['cardsn'])."%'";
	$condition .= empty($_GPC['idcard']) ? '' : " AND idcard LIKE '%".trim($_GPC['idcard'])."%'";
	$condition .= empty($_GPC['realname']) ? '' :" AND realname LIKE '%".trim($_GPC['realname'])."%'";
	$condition .= empty($_GPC['mobile'])  ? '':" AND `mobile` LIKE '%".trim($_GPC['mobile'])."%'";
	$condition .= empty($_GPC['platenumber'])  ? '':" AND `platenumber` LIKE '%".trim($_GPC['platenumber'])."%'";
	$sql ="SELECT C.uid,mobile,idcard,birthday,birthmonth,birthyear,realname,C.createtime,platenumber,cardsn ,follow,openid 
		FROM (SELECT A.uid,mobile,idcard,birthday,birthmonth,birthyear,realname,A.createtime,platenumber,cardsn FROM"
		.tablename('mc_members')." as A "
		." LEFT JOIN "
		.tablename('mc_card_members')." as B "
		." ON  A.uid=B.uid) as C "
		."LEFT JOIN "
		.tablename('mc_mapping_fans')." as D "
		." ON C.uid=D.uid "
		." WHERE C.uid!=''"
		.$condition
		." ORDER BY createtime DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
	
	$list = pdo_fetchall($sql);

	$total = pdo_fetchcolumn("SELECT COUNT(*)
		FROM (SELECT A.uid,mobile,idcard,birthday,birthmonth,birthyear,realname,A.createtime,platenumber,cardsn FROM"
		.tablename('mc_members')." as A "
		." LEFT JOIN "
		.tablename('mc_card_members')." as B "
		." ON  A.uid=B.uid) as C "
		."LEFT JOIN "
		.tablename('mc_mapping_fans')." as D "
		." ON C.uid=D.uid "
		." WHERE C.uid!=''"
		.$condition);
	$pager = pagination($total, $pindex, $psize);
	if($do == 'post')
	{
		
		$uid=intval($_GPC['uid']);
		$sql="SELECT B.uid,B.mobile,B.idcard,resideprovince,residedist,residecity,status,B.platenumber,cardsn, openid,B.realname,create_time,birthyear,birthmonth,birthday FROM ".tablename('membership_info')."AS A,".tablename('mc_members')."AS B"." WHERE B.uniacid = '{$_W['uniacid']}' "." AND B.uid={$uid} ".$condition." ORDER BY create_time DESC LIMIT 1 " ;
		$profile=pdo_fetch($sql);
		if (!empty($_GPC['submit']))
		{   
			$condition = '';
			$condition .= empty($_GPC['realname']) ? '' : " realname='{$_GPC['realname']}',";
			$condition .= empty($_GPC['birth']['year']) ? '' : "birthyear='{$_GPC['birth']['year']}',";
			$condition .= empty($_GPC['birth']['month']) ? '' : "birthmonth='{$_GPC['birth']['month']}',";
			$condition .= empty($_GPC['birth']['day']) ? '' : " birthday='{$_GPC['birth']['day']}',";
			$condition .= empty($_GPC['resid']['city']) ? '' : " residecity='{$_GPC['resid']['city']}',";
			$condition .= empty($_GPC['resid']['province']) ? '' : " resideprovince='{$_GPC['resid']['province']}',";
			$condition .= empty($_GPC['resid']['dist']) ? '' : " residedist='{$_GPC['resid']['dist']}',";
			$condition .= empty($_GPC['platenumber']) ? '' : " platenumber='{$_GPC['platenumber']}',";
			$condition .= empty($_GPC['mobile']) ? '' : " mobile='{$_GPC['mobile']}'";
		    $sql="UPDATE".tablename('mc_members')."SET ".$condition." WHERE uid='{$uid}'";
		    if(pdo_query($sql))
		 	{
		    	message('修改资料成功','','success');
		    }
		    else{
		    	message('修改资料失败','','error');
		    }
		}
		
	}
template('mc/wx_memberinfo');
?>