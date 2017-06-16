<?php
global $_W,$_GPC;

$info = array(
		"openid" => $_GPC['openid'],
		"title" => $_GPC['title'],
		"desc" => $_GPC['desc'],
		"name" => $_GPC['name'],
		"mobile" => $_GPC['mobile'],
		"address" => $_GPC['address'],
		"filename" => $_GPC['filename'],		
		'create_time' => time()
);
$result = pdo_insert('mothers_day', $info);
$id     = pdo_insertid();
if ($id) {
	echo json_encode(array('status'=>1,'id'=>$id));
} else {
	echo json_encode(array('status'=>0,'msg'=>'失败'));
}