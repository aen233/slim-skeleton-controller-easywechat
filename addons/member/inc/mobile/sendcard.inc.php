<?php
defined('IN_IA') or exit('Access Denied');
load()->classs('weixin.account');
global $_W,$_GPC;


// ORM 数据库映射组件
require_once dirname(__FILE__).'/../../idiorm/vendor/autoload.php';



//if ($_W['ispost'] && $_W['isajax']) {

        // 获取流水信息
        //$orderId = $_GPC['retailId'];
        //$orderId = '19062208256';
        $orderId = '160907910200066';

        $initTime              = 1475251200; // 2016-10-1
        $perMonthUnixTimeStamp = 2592000;

        $tel = $_GPC['tel'];
        $tel = '18192800384';

        /*
        if (date('ymd') != substr($retailId,0,6)) {
            echo json_encode(array('msg'=>'对不起，只限当日小票参加活动','status'=>0));exit();
        }
        */

        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);

        $config = $_W['config']['db'];

        ORM::configure(array(
            'connection_string' => 'mysql:host='.$config['host'].';dbname='.$config['database'],
            'username' => $config['username'],
            'password' => $config['password']
        ));

        ORM::configure('return_result_sets', true); // returns result sets
        ORM::configure('driver_options', array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

        //$str = "http://192.168.0.110/checkOrder.php?orderId=160823910100116";
        $order = file_get_contents("http://192.168.0.110/checkOrder.php?orderId=".$orderId);

        $order = prepareJSON($order);

        // @todo 如果满足实际付款金额大于3000元, 并且是A类品牌的水单. 则准备发劵,
        $localCoupon = json_decode($order, true);

            /**
                @ 1. 实际付款金额达到发劵条件
                @ 2. 检测是否为重复发劵
                @ 2.1  依据流水单号、Openid 依次检查是否为重复发劵 (一张流水单号只能创建一套卡劵)
             */
        if(1 == $localCoupon["status"]){
            //@ 检查流水单号， openid 用户数量是否 已经重复发劵
            //@ 1. 检查用户是否已经领取过劵
            //@ 2. 检查票据是否已经使用过，不能重复领取卡劵

            $openid = $_W['openid'];

            // @ 通过使用硬件扫面获取流水单号

            //$customerCouponInfo = ORM::for_table('card_card_card')->where_equal('openid',$openid)->find_one();
            //$orderInfoCoupon    = ORM::for_table('card_card_card')->where_equal('orderid',$orderId)->find_one();

            $customerCouponInfo = pdo_fetch("SELECT `id` FROM `card_card_card` WHERE `openid`='".$openid."'");
            $orderInfoCoupon    = pdo_fetch("SELECT `id` FROM `card_card_card` WHERE `orderid`= '".$orderId."'");
            $customerMobile     = pdo_fetch("SELECT `id` FROM `card_card_card` WHERE `tel` = '".$tel."'");

            $flag = 0;


            if(!empty($orderInfoCoupon)){
                exit('{"status":0, "msg":"该票据已经领取过卡劵"}');
            }

            if(!empty($customerCouponInfo)){
                exit('{"status":0, "msg":"该微信已经领取过卡劵"}');
            }

            if(!empty($customerMobile)){
                exit('{"status":0, "msg":"该手机号码已经领取过卡劵"}');
            }


            if(empty($customerCouponInfo) && empty($customerMobile) && empty($orderInfoCoupon)){

                //满足条件准备发送卡劵
                for($i = 11, $j = 0; $i <= 121; $i = $i + 10, $j++, $k++ ){
                    try{

                        $cardnum = '181'.mt_rand(10000,99999).mt_rand(10000,99999);
                        /**
                         * 满足创建卡劵的数量,开始创建卡劵数据， 根据卡劵的分组, 初始化创建卡劵状态
                        符合情况 创建事务机制
                         */

                        ORM::get_db()->beginTransaction();

                        $k = intval($j / 2);
                        $couponInfoData = ORM::for_table('card_card_card')->create();

                        /**
                         * ORM 数据库对象复制, 发劵六期套劵
                         */
                        // 卡劵码号
                        $couponInfoData->password = $cardnum;
                        // 卡劵种类
                        $couponInfoData->card_id = $i;
                        // 卡劵附属活动ID
                        $couponInfoData->act_id = 11;
                        // 是否是通过扫码添加生成的
                        $couponInfoData->is_add = 1;
                        // 卡劵使用状态
                        $couponInfoData->status = 2;

                        if(0 == $j % 2){
                            $couponInfoData->price = 300;
                        }else{
                            $couponInfoData->price = 200;
                        }

                        $couponInfoData->create_by = 1;
                        $couponInfoData->create_time = time();

                        $couponInfoData->tel = '18192800384';
                        $couponInfoData->use_by = 0;
                        $couponInfoData->use_time = '';

                        $couponInfoData->openid = $openid;
                        // 购物商品流水号码
                        $couponInfoData->orderid =  $orderId;
                        // 部组编码
                        $couponInfoData->c_dept_id = $localCoupon['c_dept_id'];

                        $brandName = ORM::for_table('card_dept_name')->where_equal('c_dept_id',$localCoupon['c_dept_id'])->find_one();
                        $couponInfoData->brand  = $brandName->get('c_dept_name');

                        /**
                         *  一期卡劵启用时间为 10 月 4 日
                         *  按照自然月计算, 此后的时间戳递增 2592000
                         */
                        if($j == 0 || $j == 1){
                            // 2016-10-4
                            $couponInfoData->start_time = date("Y-m-d",1475510400);

                            $formatTime = 1475510400+ $perMonthUnixTimeStamp ;
                            $couponInfoData->end_time   = date("Y-m-d",$formatTime);
                        }else{
                            $formatTime = $initTime + $perMonthUnixTimeStamp * $k;
                            $couponInfoData->start_time = date("Y-m-d",$formatTime);

                            $formatTime = $initTime + $perMonthUnixTimeStamp * $k + $perMonthUnixTimeStamp;
                            $couponInfoData->end_time   = date("Y-m-d",$formatTime);
                        }

                        $couponInfoData->orderPrice = $localCoupon['orderPrice'];
                        $couponInfoData->r_way = 2;

                        $couponInfoData->dept_amount = $localCoupon['dept_amount'];
                        $couponInfoData->ap_total    = $localCoupon['apTotal'];

                        $result = $couponInfoData->save();

                        if($result){
                            $flag++;
                        }else{
                            $flag = $flag;
                        }

                        ORM::get_db()->commit();

                    }catch (PDOException $e){
                        logs($e->getMessage(),'Coupons');
                        ORM::get_db()->rollBack();
                    }
                }

                if(12 == $flag){
                    exit('{"status":1, "msg":"已经领取成功!"}');
                }else{
                    //@todo 已经领取卡劵状态
                    exit('{"status":0,"msg":"出了一些问题，请稍后重试"}');
                }
            }

        }else{
            //@todo 未满足领卡劵状态
            exit('status":-1, "msg":"支付金额为满足条件, 不能领取卡劵"}');
        }

        /**
        if ($order['status']) {
            // 判断价格是否在区间范围
            $orderPrice = $order['price'];
            if ($orderPrice < 500) {
                echo json_encode(array('msg'=>'对不起，您的实付金额未满500元','status'=>0));exit();
            }
            // 判断流水号是否重复领取
            $checkCard = pdo_fetchcolumn('select password from card_card_card where orderid = :orderid', array('orderid'=>$retailId));

            if ($checkCard) {
                echo json_encode(array('msg' => '对不起，您已经领取过，请勿重复领取', 'status' => 0));
                exit();
            }

            // 获取商户名称
            $deptId = $order['deptId'];
            $brand = pdo_fetchcolumn('select brand from card_dept where c_dept_id = :dept', array('dept'=>$deptId));
            if (empty($brand)) {
                echo json_encode(array('msg' => '对不起，本活动只限新进品牌', 'status' => 0));
                exit();
            }

            // 优惠券金额
            $cardPrice = floor($orderPrice/500)*50;

            // 获取手机号
            $mobile = '';
            if ($_W['fans']['follow'] && $_W['fans']['uid']) {
                $mobile = $_W['member']['mobile'];
            }

            $data = array(
                'price'      => $cardPrice,
                'tel'        => $mobile,
                'openid'     => $_W['openid'],
                'orderid'    => $retailId,
                'c_dept_id'  => $deptId,
                'brand'      => $brand,
                'start_time' => date('Y-m-d', strtotime('+1 day', strtotime($order['dateTime']))),
                'end_time'   => '2015-10-31',
                'orderPrice' => $orderPrice,
                'status' => 1,
                'r_way'      => 2
            );
            echo  json_encode($data);
            exit();
        } else {
            echo json_encode(array('status'=>0,'msg'=>'对不起，未找到相关商品信息，可能是因为条码打印不清晰导致，请您至1楼服务台进行人工服务'));
        }
        */
    //} else {

    //    include $this->template('member/sendCard');
    //}



function prepareJSON($input){
    $input = mb_convert_encoding($input,'UTF-8','ASCII,UTF-8,ISO-8859-1');
    if(substr($input,0,3) == pack("CCC",0xEF,0xBB,0xBF)) $input = substr($input,3);
    return $input;
}