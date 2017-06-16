<?php
/**
 * 便利店模块定义
 *
 * @author Gorden
 * @url http://bbs.we7.cc/
 */
defined('IN_IA') or exit('Access Denied');

class We7_storeModule extends WeModule {

	public function settingsDisplay($settings) {
		
		// 声明为全局才可以访问到.
		global $_W, $_GPC;
		
		if(checksubmit()) {
			
			// $_GPC 可以用来获取 Cookies,表单中以及地址栏参数
			$data = $_GPC['data'];
			
			// message() 方法用于提示用户操作提示
			empty($data['name']) && message('请填写便利店名称');
			empty($data['logo']) && message('请填写便利店 LOGO');
			empty($data['linkman']) && message('请填写便利店联系人');
			empty($data['phone']) && message('请填写便利店联系电话');
			empty($data['address']) && message('请填写便利店地址');
			empty($data['description']) && message('请填写便利店介绍');
			
			//字段验证, 并获得正确的数据$dat
			if (!$this->saveSettings($data)) {
				message('保存信息失败','','error');
			} else {
				message('保存信息成功','','success');
			}
		}
		
		// 模板中需要用到 "tpl" 表单控件函数的话, 记得一定要调用此方法.
		load()->func('tpl');
		
		//这里来展示设置项表单
		include $this->template('setting');
	}

}