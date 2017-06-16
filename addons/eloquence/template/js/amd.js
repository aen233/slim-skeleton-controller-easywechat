/**
 * 路径: addons/we7_store/template/js/amd.js
 */

define(['util'], function(u){
	var module = {};
	
	module.msg = function(message){
		u.message(message);
	}
	
	return module;
});