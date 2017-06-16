<?php
global $_W,$_GPC;
switch( $_GPC['act_id'] ){
	case '20160624':
		$tableName = "0624_day";
	break;
	case '20160714':
		$tableName = "20160714_day";
		break;
	default:
		$tableName = $_GPC['act_id']."_day";
		break;
}
if($_GPC['act_id'] == '20160809'){
	$tableName = "match_item";
	$description = array("职业"=>$_GPC['zy'],"爱好"=>$_GPC['ah'],"标签"=>$_GPC['bq'],"脱单宣言"=>$_GPC['desc']);
	$description = json_encode($description);
	$symboldesc = pdo_fetch( " select * from ims_symbolstar_grp where star_id = :star_id ",array("star_id"=>$_GPC['symbol_star_id']) );
	switch($_GPC['xb']){
		case "男":
			$require_sex = 1;
			break;
		case "女":
			$require_sex = 0;
			break;
	}
	if($require_sex == 1){
		switch(str_replace(' ','',$_GPC['wnl'])){
			case "比我大的成熟大叔":
				$require_age = 1;
				break;
			case "同我差不多大的进取青年":
				$require_age = 2;
				break;
			case "比我小的多汁小鲜肉":
				$require_age = -1;
				break;
			case "年龄无所谓":
				$require_age = 0;
				break;
		}
		switch(str_replace(' ','',$_GPC['wlx'])){
			case "沉稳上进": $require_star_id = 9; break;
			case "智慧傲人": $require_star_id = 6; break;
			case "浪漫柔情": $require_star_id = 12; break;
			case "注重情义": $require_star_id = 1; break;
			case "多金潜力股": $require_star_id = 7; break;
			case "幽默段子手": $require_star_id = 4; break;
			case "居家暖男": $require_star_id = 10; break;
			case "霸道总裁": $require_star_id = 2; break;
			case "完美主义者": $require_star_id = 8; break;
			case "颜控大长腿": $require_star_id = 5; break;
			case "善良执著": $require_star_id = 11; break;
			case "阳光大男孩": $require_star_id = 3; break;
		}
	}else{
		switch(str_replace(' ','',$_GPC['mnl'])){
			case "比我大的丰韵熟女":
				$require_age = 1;
				break;
			case "同我差不多大的独立御姐":
				$require_age = 2;
				break;
			case "比我小的清新萝莉":
				$require_age = -1;
				break;
			case "年龄无所谓":
				$require_age = 0;
				break;
		}
		switch(str_replace(' ','',$_GPC['mlx'])){
			case "内敛矜持": $require_star_id = 9; break;
			case "理性真诚": $require_star_id = 6; break;
			case "善解人意": $require_star_id = 12; break;
			case "活力四射": $require_star_id = 1; break;
			case "理财小能手": $require_star_id = 7; break;
			case "聪明独立": $require_star_id = 4; break;
			case "贤妻良母": $require_star_id = 10; break;
			case "慷慨霸气": $require_star_id = 2; break;
			case "恬静细腻": $require_star_id = 8; break;
			case "天然无公害": $require_star_id = 5; break;
			case "气质迷人": $require_star_id = 11; break;
			case "乐天可爱": $require_star_id = 3; break;
		}
	}
	$info = array(
			"openid" => $_GPC['openid'],
			"nickname" => $_GPC['nickname'],
			"mobile" => $_GPC['mobile'],
			"age" => $_GPC['age'],
			"desc_img" => $_GPC['desc_img'],
			"description" => $description,
			"symboldesc" => $symboldesc['star_desc'],
			"symbol_star_id" => $_GPC['symbol_star_id'],
			"require_star_id" =>$require_star_id,
			"require_age" => $require_age,
			"require_sex" => $require_sex		
	);

	if(1 == $require_sex){
		$info['sex'] = 0;
	}else{
		$info['sex'] = 1;
	}



	//file_put_contents("log.txt", implode("\r\n",$info));
	$result = pdo_insert($tableName, $info);
	$id     = pdo_insertid();
}else{
$info = array(
		"openid" => $_GPC['openid'],
		"title" => $_GPC['title'],
		"desc" => $_GPC['desc'],
		"name" => $_GPC['name'],
		"mobile" => $_GPC['mobile'],
		"address" => $_GPC['address'],
		'create_time' => time(),
		'to_openid' =>'',
		'isAgree' => '',
		"filename1" => $_GPC['filename1'],
		"filename2" => $_GPC['filename2'],
		"filename3" => $_GPC['filename3'],
		"filename4" => $_GPC['filename4'],
		"filename5" => $_GPC['filename5'],
		"filename6" => $_GPC['filename6'],
		"filename7" => $_GPC['filename7'],
		"filename8" => $_GPC['filename8'],
		"filename9" => $_GPC['filename9'],
		"template" => "template1",
		"status" => 0
);
	$result = pdo_insert($tableName, $info);
	$id     = pdo_insertid();
}
if ($id) {
	echo json_encode(array('status'=>1,'id'=>$id,'act_id'=>$_GPC['act_id']));
} else {
	echo json_encode(array('status'=>0,'msg'=>'失败'));
}