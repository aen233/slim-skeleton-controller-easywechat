<?php
defined('IN_IA') or exit('Access Denied');
global $_W;

$userinfo = mc_oauth_userinfo();

echo '<pre>';
print_r($_W);
print_r($userinfo);