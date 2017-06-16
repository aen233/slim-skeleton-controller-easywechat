<?php
global $_W,$_GPC;
$time = date('Ymd');
switch( $_GPC['act_id'] ){
	case '20160624':
		$filePath =  '../temp/activity_20160624/'.$time.'/';
		break;
		case '20160714':
		$filePath =  '../temp/activity_20160714/'.$time.'/';
		break;
		case '20160809':
		$filePath =  '../temp/activity_20160714/'.$time.'/';
		break;
		case '20160901':
		$filePath =  '../temp/activity_20160714/'.$time.'/';
		break;
}
$token = $this->getToken();
$url      = 'http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=' . $token . '&media_id=' . $_GPC['serverId'];
$file     = file_get_contents($url);
$fileName =$_GPC['serverId'].'.jpg';
$this->createDir($filePath);
$input = file_put_contents($filePath.$fileName,$file);
if ($input) {
	echo json_encode(array('status'=>1,'filename'=>$filePath.$fileName));
} else {
	echo json_encode(array('status'=>0,'msg'=>'文件上传失败'));
}