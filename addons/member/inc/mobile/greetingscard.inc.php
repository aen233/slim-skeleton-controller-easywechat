<?php
defined('IN_IA') or exit('Access Denied');
load()->func('file');
global $_W,$_GPC;
mc_oauth_userinfo();
if ($_W['ispost'] && $_W['isajax']) {
    $weiObj = WeAccount::create(4);
    $token = $weiObj->fetch_token();

    $url      = 'http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=' . $token . '&media_id=' . $_GPC['serverId'];
    $file     = file_get_contents($url);
    $time = date('Ymd');
    $filePath = ATTACHMENT_ROOT . '/card/' . $time . '/' . $_W['openid'] . '/src/';
    $fileName = random(30).'.jpg';
    mkdirs($filePath);
    $input = file_put_contents($filePath.$fileName,$file);
    if ($input) {
        $data = array(
            'openid'=>$_W['openid'],
            'src_img'=>'/attachment/card/'.$time.'/'.$_W['openid'].'/src/'.$fileName,
            'created_at'=>time()
        );
        $result = pdo_insert('mc_members_greeting',$data);
        $id = pdo_insertid();
        if ($id) {
            echo json_encode(array('status'=>1,'id'=>$id));
        } else {
            echo json_encode(array('status'=>0,'msg'=>'文件上传失败'));
        }
    } else {
        echo json_encode(array('status'=>0,'msg'=>'文件上传失败'));
    }
} else {
    require $this->template('member/greetingsCard');
}