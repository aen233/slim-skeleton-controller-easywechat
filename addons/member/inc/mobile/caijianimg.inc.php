<?php
defined('IN_IA') or exit('Access Denied');
load()->func('file');
global $_W,$_GPC;

$info = pdo_fetch('select * from ims_mc_members_greeting where id = :id',array('id'=>$_GPC['id']));

if ($_W['ispost'] && $_W['isajax']) {
    $width  = $_GPC['width'];
    $height = $_GPC['height'];
    $x      = $_GPC['x'];
    $y      = $_GPC['y'];

    $srcImg = IA_ROOT.$info['src_img'];

    /*读取图片 */
    $im = imagecreatefromjpeg($srcImg);

    /* 图片要截多少, 长/宽 */
    $new_img_width  = $width ? $width : 300;
    $new_img_height = $height ? $height : 300;
    $x = $x ? $x : 350;
    $y = $y ? $y : 160;

    /* 先建立一个 新的空白图片档 */
    $newim = imagecreatetruecolor(300, 300);

    // 输出图要从哪边开始x, y , 原始图要从哪边开始 x, y , 要输多大 x, y(resize) , 要抓多大 x, y
    imagecopyresampled($newim, $im, 0, 0, $x, $y, 300, 300, $new_img_width, $new_img_height);

    /* 保存图片 */
    $time = date('Ymd');
    $filePath = ATTACHMENT_ROOT . '/card/' . $time . '/' . $info['openid'] . '/thumb/';
    $fileName = random(30).'.jpg';
    mkdirs($filePath);

    $thumb = ImageJpeg($newim,$filePath.$fileName,100);
    if ($thumb) {
        $up = pdo_update('mc_members_greeting',array('thumb_img'=>'/attachment/card/'.$time.'/'.$info['openid'].'/thumb/'.$fileName),array('id'=>$info['id']));
        if ($up) {
            /* 资源回收 */
            imagedestroy($newim);
            imagedestroy($im);
            echo json_encode(array('status'=>1,'id'=>$info['id']));
        } else {
            echo json_encode(array('status'=>0,'msg'=>'裁剪图片失败'));
        }
    } else {
        echo json_encode(array('status'=>0,'msg'=>'裁剪图片失败'));
    }
} else {
    require $this->template('member/caijian');
}