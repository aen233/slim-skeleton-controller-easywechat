<?php

namespace  App\Controllers;

/**
 * Class Index
 * @package App\Controllers
 */
class WeChat extends Controller
{
    public function getToken()
    {
        $access_token =$this->wechat->access_token->getToken();
        echo  $access_token;
    }

    public function serve(){
        $server = $this->wechat->server;
        $server->setMessageHandler(function ($message) {

            return "您好，欢迎关注！ ";
        });
        $server->serve()->send();

    }



    public function getuser(){
        $userservice=$this->wechat->user;
        $users=$userservice->lists();
        echo "<pre>";
        $data= $users->data;
        $openids=$data['openid'];
        var_dump($openids);
         var_dump($userservice->batchGet($openids));
    }

    public function createcard()
    {
        $card =$this->wechat->card;
        $cardType = 'MEMBER_CARD';
        $baseInfo = [
            'pay_info'=>[
                'swipe_card'=>[
                    'is_swipe_card'=>true
                ]
            ],
            'logo_url' => 'http://mmbiz.qpic.cn/mmbiz_jpg/6Qs5bXJZ38IEUCzPXamUgY71YgjyychRzguvOTujR8WWH5hVZeR2Aawt9RXdxRpbju2nSHGwMI44vicX4O7Daibg/0',
            'brand_name' => '毕设测试会员卡',
            'code_type' => 'CODE_TYPE_QRCODE',
            'title' => 'saga',
            'color' => 'Color010',
            'notice' => '使用时请出示此券',
            'service_phone' => '13319263050',
            'description' => "测试不可与其他优惠同享",
            'date_info' => [
                'type' => 'DATE_TYPE_PERMANENT'
            ],
            'sku' => [
                'quantity' => '100', //自定义code时设置库存为0
            ],
            //'location_id_list' => ['461907340'],  //获取门店位置poi_id，具备线下门店的商户为必填
            'get_limit' => 5,
            'use_custom_code' => false, //自定义code时必须为true
            'can_share' => true,
            'can_give_friend' => false,
            'custom_url' => 'http://www.qq.com',
            'custom_url_sub_title' => 'saga',
            'promotion_url_name' => 'saga',
            'promotion_url' => 'http://www.qq.com',
            'source' => '毕业设计',
        ];
        $especial = [
            "auto_activate"=> false,
            "wx_activate"=> true,
            "wx_activate_after_submit" =>true,
            "background_pic_url" =>'http://mmbiz.qpic.cn/mmbiz_png/6Qs5bXJZ38LxrnPkdZvp4JMNjB238LeYeyWkia5PKGpxE9whSqeJtxqZTDyvq5rY4WXtuWdd1qOdChQxevibpMqw/0?wx_fmt=jpeg',
            "supply_bonus"=>true,
            "supply_balance"=>false,
            "prerogative"=> "尊享品牌满4000减1000，满600减100
                              折上满减、可拼单累计、以此类推
                              凭小票至各楼层收银台结算
                              单件商品不可拆分开具小票
                              不与商场其他活动共享
                              特价商品除外
                              优先就餐
                              就餐优先免排队
                              免费停车
                              本中心广场、空中花园停车场随意免费停车不限时。
                              全程导购专享服务及免费包装礼品",
        ];
        $result = $card->create($cardType, $baseInfo, $especial);
        var_dump($result);
        $cardId= $result->get('card_id');
        $requiredForm = [
            'required_form' => [
                'common_field_id_list' => [
                    "USER_FORM_INFO_FLAG_MOBILE",
                    "USER_FORM_INFO_FLAG_NAME",
                    "USER_FORM_INFO_FLAG_IDCARD"
                ]
            ]
        ];
        $optionalForm = [];
        $card->activateUserForm($cardId, $requiredForm, $optionalForm);
        return $cardId;
    }

    public function WXQR(){
        $card = $this->wechat->card;
        $cards = [
            'action_name' => 'QR_CARD',
            'expire_seconds' => 1800,
            'action_info' => [
                'card' => [
                    'card_id' => $this->createcard(),
                    'is_unique_code' => false,
                    'outer_id' => 1,
                ],
            ],
        ];
        $show_qrcode_url= $card->QRCode($cards)->get('show_qrcode_url');
        echo "<script>location.href='$show_qrcode_url'</script>";
    }
}



