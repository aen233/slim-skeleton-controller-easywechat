<?php
function isWechatRequest(){
	$userAgent = $_SERVER['HTTP_USER_AGENT'];;
	//判断是否为微信浏览器访问
	if(strpos($userAgent,'MicroMessenger') === false){
		exit('对不起，请您在微信浏览器下进行访问！');
	}
}

if (!function_exists('logs')) {
    /**
     * logs
     */
    function logs($line, $filename = 'test'){
        include IA_ROOT.'/soap/customer/service/class/KLogger.class.php';
        $dir = IA_ROOT.'/logs/'.$filename.'/';
        if (createDir($dir)){
            $logs = new KLogger($dir.date('Y-m-d').'.log',KLogger::DEBUG);
            $logs->LogInfo($line);
        }
    }

}

if (!function_exists('createDir')) {
    function createDir($dir){
        return is_dir($dir) or (createDir(dirname($dir)) and @mkdir($dir, 0777));
    }

}