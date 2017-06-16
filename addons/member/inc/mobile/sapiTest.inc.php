<?php
/**
 *
 */

$name='家扥';
$idNum='440901198301218952';
$tel= '17777777467';



$RegParam=array(
    'name'=>$name,
    'idNum'=>$idNum,
    'tel'=>$mobile,
    'idType'=>'H'
);


$custInfo = $this->apiCustRegsiter($RegParam);


var_dump($custInfo);