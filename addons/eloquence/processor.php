<?php
/**
 * 便利店模块处理程序
 *
 * @author Gorden
 * @url http://bbs.we7.cc/
 */
defined('IN_IA') or exit('Access Denied');

class We7_storeModuleProcessor extends WeModuleProcessor {
	public function respond() {
		$content = $this->message['content'];
		
		//这里定义此模块进行消息处理时的具体过程, 请查看微擎文档来编写你的代码
		global $_W;
		
		$s = '==== message ==== '.PHP_EOL;
		foreach ($this->message as $k => $v){
			$s .= "{$k} : {$v}" . PHP_EOL;
		}
		
		return $this->respText($s);
	}
}