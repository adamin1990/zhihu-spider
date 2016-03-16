#!/usr/bin/php
<?php
/**
 * 知乎 爬虫 用户自动登录验证 获取登录cookie
 * 
 * @author  Yang,junlong at 2016-03-16 21:46:50 build.
 * @version $Id$
 */

require_once 'Http.class.php';
require_once 'Mysql.class.php';
require_once 'simple_html_dom.php';

function checkLogin() {
	$login_cookie = getLoginCookie();

	$http = new Http('https://www.zhihu.com/', array('request_headers' => array('Cookie'=>$login_cookie)));
	$dom = new simple_html_dom();

	$http->get(function($response_body, $response_headers, $http) use($dom){
		$html = $dom->load($response_body);

		$login_flag = $html->find('.sign-button', 0);
		$_xsrf = $html->find('input[name="_xsrf"]', 0)->value;

		$set_cookie = $response_headers['set_cookie'];

		$cookies = array();
		foreach ($set_cookie as  $cookie) {
			$cookie = explode(';', $cookie);
			$cookie = explode('=', $cookie[0]);
			$cookies[$cookie[0]] = $cookie[1];
		}

		if($login_flag) {
			// 需要登录
			$data = array(
				'email' => 'crossyou2009@gmail.com',
				'password' => '879150',
				'remember_me' => 'true',
				'_xsrf' => $_xsrf
			);
			$http->post('https://www.zhihu.com/login/email', $data, function($response_body, $response_headers, $http) use(&$cookies){
				$set_cookie = $response_headers['set_cookie'];

				foreach ($set_cookie as  $cookie) {
					$cookie = explode(';', $cookie);
					$cookie = explode('=', $cookie[0]);
					$cookies[$cookie[0]] = $cookie[1];
				}

				$cookies_array = array();
				foreach ($cookies as $key => $value) {
					$cookies_array[]= $key.'='.$value;
				}

				file_put_contents('login_cookie', join(';', $cookies_array));
			});
		}
	});
}

function getLoginCookie() {
	$login_cookie = '';
	if(file_exists('login_cookie')) {
		$login_cookie = file_get_contents('login_cookie');
	}

	return $login_cookie;
}

checkLogin();