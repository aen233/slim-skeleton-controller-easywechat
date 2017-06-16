<?php
global $_W,$_GPC;
switch( $_GPC['act_id'] ){
	case '20160601':
		$tableName = "0601_day";
	break;
}
logs(" update ". tablename($tableName) ." set template = '".$_GPC['template']."' where id = '".$_GPC['id']."' ");
pdo_run(" update ". tablename($tableName) ." set template = '".$_GPC['template']."' where id = '".$_GPC['id']."' ");
echo json_encode(array('status'=>1,'id'=>$_GPC['id']));
