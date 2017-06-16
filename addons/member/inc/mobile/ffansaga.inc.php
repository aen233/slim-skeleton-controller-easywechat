<?php



class FFantosagasync
{
    /**
     * 操作组件请求接口常量
     */
    // 操作组件新建会员接口地址
    const NEW_MEMBER_INTER = 'http://api.ffan.com/ffan/v1/cop/user';

    // 编辑会员信息接口地址
    const EDIT_MEMBER_INITER = 'http://api.ffan.com/ffan/v1/cop/cuser';

    // 修改手机号接口地址
    const MOD_MEMBER_MOBILE_INITER = 'http://api.ffan.com/v1/cop/ffan/v1/cop/mobile';
    /**
     *  根据接口地址发送JSON 会员信息数据。
     */
    public function postSagaCustomerInfo($param, $url, $secondTimeout = 300){
        $oCurl = curl_init();
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (is_string($param)) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach($param as $key=>$val){
                $aPOST[] = $key."=".urlencode($val);
            }
            $strPOST =  join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($oCurl, CURLOPT_POST,true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if(intval($aStatus["http_code"])==200){
            return $sContent;
        }else{
            return false;
        }
    }

    public function combineInfoByAssoc($params, $option = null){
        if(array_keys($params) !== range(0, count($params)-1) && null == $option){
            return json_encode($params);
        }else{
            return json_encode($params, $option);
        }
    }



    public function sendMsger()
    {
        $ffanToSagaInfo = $this->datainfo();
        $sendData = $this->combineInfoByAssoc($ffanToSagaInfo);

        var_dump($sendData);
        $rets = $this->postSagaCustomerInfo($sendData,self::NEW_MEMBER_INTER);

        return $rets;
    }
}