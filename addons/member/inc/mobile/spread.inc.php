<?php
require_once IA_ROOT.'/framework/library/phpexcel/PHPExcel.php';
global $_W,$_GPC;



if ($_W['ispost']) {
    if (empty($_GPC['start']) || empty($_GPC['end'])) {
        echo '对不起，日期不能为空';exit();
    }

    if (!empty($_GPC['groupId'])) {
        $where[] = "s.shopname  = '{$_GPC['groupId']}'";
    }

    $start = strtotime($_GPC['start']);
//    $end = strtotime($_GPC['end'])+86399;
    $end = strtotime($_GPC['end']);
    $where[] =  "s.created_at between {$start} and {$end}";

    $condition = (count($where) > 0 ) ? ' and '.implode(' and ',$where) : '';

    $sql = "SELECT s.shopname,shop.shopinfo,COUNT(*) as total,COUNT(CASE f.follow WHEN 1 THEN 1  END) as 'follow',COUNT(CASE f.follow WHEN 0 THEN 1  END) as 'unfollow',COUNT(CASE WHEN f.uid >0 THEN 1 END ) as 'regist' FROM ims_mc_spread as s,ims_mc_mapping_fans  as f,ims_mc_spread_shop as shop WHERE s.openid = f.openid AND s.shopname = shop.shopname AND s.`status` = 0 {$condition} GROUP BY s.shopname ORDER BY total DESC";
    $regist =  pdo_fetchall($sql);

    $bindSql = "SELECT a.shopname,COUNT(*) as bind FROM (SELECT s.shopname,f.openid FROM ims_mc_spread as s,ims_mc_mapping_fans  as f,ims_mc_members as m WHERE s.openid = f.openid AND f.follow = 1 AND f.uid = m.uid  AND (m.platenumber <> '' OR m.platenumber2 <> '' OR m.platenumber3 <> '') AND s.`status` = 0 {$condition}  ORDER BY s.shopname ASC) as a GROUP BY a.shopname ORDER BY a.shopname ASC";
    $bind = pdo_fetchall($bindSql);

    $parkSql = "SELECT a.shopname,COUNT(*) as park FROM (SELECT s.shopname,f.openid FROM ims_mc_spread as s , ims_mc_mapping_fans as f, ims_park_member as p WHERE s.openid = f.openid AND f.follow = 1 AND f.uid = p.uid AND p.score < 1000 AND s.`status` = 0 {$condition}  ORDER BY s.shopname ASC) as a GROUP BY a.shopname ORDER BY a.shopname ASC";
    $park = pdo_fetchall($parkSql);

    foreach ($bind as $key => $value) {
        $bindData[$value['shopname']] = $value['bind'];
    }

    foreach ($park as $key => $value) {
        $pardData[$value['shopname']] = $value['park'];
    }

    $total = array();
    foreach ($regist as $key => $value) {
        $exist = array_key_exists($value['shopname'],$bindData);
        $regist[$key]['bind'] = $exist ? $bindData[$value['shopname']] : 0;

        $existPark = array_key_exists($value['shopname'], $pardData);
        $regist[$key]['park'] = $existPark ? $pardData[$value['shopname']] : 0;
    }

    array_walk($regist,function($value,$key) use(&$total){
        $total['shop'] = $key + 1;
        $total['total'] += $value['total'];
        $total['follow'] += $value['follow'];
        $total['unfollow'] += $value['unfollow'];
        $total['regist'] += $value['regist'];
        $total['bind'] += $value['bind'];
        $total['park'] += $value['park'];
    });

    $phpexcel = PHPExcel_IOFactory::createReader('Excel5')->load(MODULE_ROOT.'/spread.xls');
    $objActSheet = $phpexcel->getActiveSheet();
    $objActSheet->setCellValue ( 'B3','查询日期： '.$_GPC['start'].'~'.$_GPC['end'] );
    $objActSheet->setCellValue ( 'B4','参与商户 '.$total['shop'].' 家' );
    $objActSheet->setCellValue ( 'C4',$total['total'].' 人' );
    $objActSheet->setCellValue ( 'D4',$total['follow'].' 人' );
    $objActSheet->setCellValue ( 'E4',$total['unfollow'].' 人' );
    $objActSheet->setCellValue ( 'F4',$total['regist'].' 人' );
    $objActSheet->setCellValue ( 'G4',$total['bind'].' 人' );
    $objActSheet->setCellValue ( 'H4',$total['park'].' 次' );
    $baseRow = 7;
    foreach($regist as $key => $value) {
        $objActSheet->setCellValue ( 'A'.$baseRow,$key+1 );
        $objActSheet->setCellValue ( 'B'.$baseRow,$value['shopinfo'] );
        $objActSheet->setCellValue ( 'C'.$baseRow,$value['total'] );
        $objActSheet->setCellValue ( 'D'.$baseRow,$value['follow'] );
        $objActSheet->setCellValue ( 'E'.$baseRow,$value['unfollow'] );
        $objActSheet->setCellValue ( 'F'.$baseRow,$value['regist'] );
        $objActSheet->setCellValue ( 'G'.$baseRow,$value['bind'] );
        $objActSheet->setCellValue ( 'H'.$baseRow,$value['park'] );
        $baseRow++;
    }
    ob_end_clean();
    $filename = iconv('UTF-8', 'GB2312', '微信推广数据 '.$_GPC['start'].'~'.$_GPC['end']);
    header ( 'Content-Type: application/vnd.ms-excel' );
    header ('content-Type:application/vnd.ms-excel;charset=utf-8');
    header ( 'Content-Disposition: attachment;filename="' . $filename . '.xls"' ); //"'.$filename.'.xls"
    header ( 'Cache-Control: max-age=0' );
    $objWriter = PHPExcel_IOFactory::createWriter ( $phpexcel, 'Excel5' ); //在内存中准备一个excel2003文件
    $objWriter->save ( 'php://output' );
} else {
    include $this->template('member/spread');
}
