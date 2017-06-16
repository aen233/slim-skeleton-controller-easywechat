<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
load()->classs('CardApi');
$do=$_GPC['do'];
$card=new CardApi;
$pindex = max(1, intval($_GPC['page']));
	$psize = 40;
	$condition = '';
	$condition .= empty($_GPC['cardsn']) ? '' : " AND `cardsn` LIKE '%".trim($_GPC['cardsn'])."%'";
	$condition .= empty($_GPC['mobile']) ? '' : " AND `mobile` LIKE '%".trim($_GPC['mobile'])."%'";
	$condition .= empty($_GPC['uid']) ? '' : " AND B.uid=".trim($_GPC['uid']);
	$condition .= empty($_GPC['realname']) ? '' :" AND realname LIKE '%".trim($_GPC['realname'])."%'";
	
/*	
 *   分别查询ERP,与本地数据库的 购物，停车积分
 *  
 */
/*if(!empty($_GPC['type']))
	{
		if($_GPC['type']=='buy'){
		$sql = "SELECT B.uid,mobile,cardsn, realname,custid FROM ".tablename('mc_members_score')."AS A,".tablename('mc_members')."AS B"." WHERE B.uniacid = '{$_W['uniacid']}' "." AND A.uid=B.uid ".$condition." ORDER BY create_time DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
		$arr = pdo_fetchall($sql);
		foreach($arr as $key=>$value){
			$score=array();
			$score['score']=$card->getCustomerScore($value['custid']);
			$result=array_merge($value,$score);
			$list[]=$result;
			
		}
		$total = pdo_fetchcolumn("SELECT COUNT(*)  FROM "
								.tablename('mc_members_score')."AS A,"
								.tablename('mc_members')."AS B"
								." WHERE B.uniacid = '{$_W['uniacid']}' "
								." AND A.uid=B.uid ".$condition);
		
		}else{
			$sql="SELECT uid,mobile, realname,score,openid FROM ".tablename('park_member').$condition." ORDER BY create_time DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
			$list = pdo_fetchall($sql);
			$total = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('park_member').$condition);
		}	
	}*/
	$sql = "SELECT B.uid,mobile,cardsn,B.openid,B.create_time,realname,A.score as pscore,B.score as bscore FROM "
			.tablename('park_member')." AS A "
			." LEFT JOIN "
			.tablename('mc_members_score')." AS B "
			." ON A.uid=B.uid WHERE B.uid!='' "
			.$condition
			." ORDER BY B.create_time DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize;
	$list=pdo_fetchall($sql);
	$total=pdo_fetchcolumn("SELECT COUNT(*) FROM "
			.tablename('park_member')." AS A "
			." LEFT JOIN "
			.tablename('mc_members_score')." AS B "
			." ON A.uid=B.uid WHERE B.uid!='' "
			.$condition);
	$pager = pagination($total, $pindex, $psize);
if($do=='modify'){
	$uid=$_GPC['uid'];
	$sql = "SELECT cardsn, realname, A.score as pscore,B.score as bscore FROM "
			.tablename('park_member')."AS A,"
			.tablename('mc_members_score')."AS B"
			." WHERE A.uid='{$uid}' AND B.uid='{$uid}'";
	$profile=pdo_fetchall($sql);
/*	echo "<pre>";
	var_dump($profile);
	echo "</pre>";
	exit;*/
}
//积分的更新
if($_GPC['mscore']!=''){
	$suid=$_GPC['suid'];
    $psadd=$_GPC['psadd'];			//停车积分增量
	$pssub=$_GPC['pssub'];			//停车积分减量
	$bsadd=$_GPC['bsadd'];			//购物积分增量
	$bssub=$_GPC['bssub'];			//购物积分减量
	$mpscore=$_GPC['mpscore'];		//当前停车积分
	$mbscore=$_GPC['mbscore'];		//当前购物积分
	$cardid=$_GPC['cardsn'];
	$date= date('Y-m-d H:i:s');
	if(!empty($psadd) || (!empty($pssub))){
		if(!empty($psadd))
		{
			$score=$psadd+$mpscore;
			$sql_log="INSERT INTO".tablename('member_score_log')
				."(uid,cardsn,score_type,score_num,operator,time) VALUES"
				."('{$uid}','{$cardid}','停车积分','+{$psadd}','{$_W['username']}','{$date}')";
		}else{
			$score=$mpscore-$pssub;
			$sql_log="INSERT INTO".tablename('member_score_log')
				."(uid,cardsn,score_type,score_num,operator,time) VALUES"
				."('{$uid}','{$cardid}','停车积分','-{$pssub}','{$_W['username']}','{$date}')";
		}
		$sql="UPDATE".tablename('park_member')."SET score='{$score}' WHERE uid='{$suid}'";
		if(pdo_query($sql))
		{
			if(pdo_query($sql_log))
			{
				echo "<script>
				alert('停车积分修改并记录成功!');
				history.go(-1);
				</script>";	
			}
		
		}
		else{
			echo "<script>
			alert('停车积分修改失败!');
			history.go(-1);
		</script>";	
		}
	}
	
	if(!empty($bsadd) || (!empty($bssub))){
		if(!empty($bsadd))
		{
			$score=+$bsadd;
			$sql_log_erp="INSERT INTO".tablename('member_score_log')
				."(uid,cardsn,score_type,score_num,operator,time) VALUES"
				."('{$uid}','{$cardid}','购物积分','+{$bsadd}','{$_W['username']}','{$date}')";
		}else{
			$score=-$bssub;
			$sql_log_erp="INSERT INTO".tablename('member_score_log')
				."(uid,cardsn,score_type,score_num,operator,time) VALUES"
				."('{$uid}','{$cardid}','购物积分','-{$bssub}','{$_W['username']}','{$date}')";
		}
		$result=$card->setCustomerScore($cardid, $score);
		if($result=='ture')
		{
			$nscore=$score+$mbscore;
			$sql_erp="UPDATE".tablename('mc_members_score')."SET score='{$nscore}' WHERE uid='{$suid}'";
			if(pdo_query($sql_erp))
			{
				if(pdo_query($sql_log_erp))
				{
				echo "<script>
				alert('购物积分修改并记录成功!');
				history.go(-1);
				</script>";	
				}
			}
			else{
				echo "<script>
				alert('购物积分修改失败!');
				history.go(-1);
				</script>";	
			}
		}else{
			message('ERP接口调用失败','','error');
		}
	}
	
}
template('mc/wx_memberscore');
?>