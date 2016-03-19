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

		if(empty($response_headers['set_cookie'])) {
			return;
		}

		$set_cookie = $response_headers['set_cookie'];

		$cookies = array();
		foreach ($set_cookie as  $cookie) {
			$cookie = explode(';', $cookie);
			$cookie = $cookie[0];
			$cookies[] = $cookie;
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
					$cookie = $cookie[0];
					$cookies[] = $cookie;
				}

				$cookies_array = array();
				foreach ($cookies as $value) {
					$cookies_array[]= $value;
				}

				file_put_contents(getCookieFile(), join(';', $cookies_array));
			});
		}
	});
}

function getLoginCookie() {
	$login_cookie = '';
	$cookie_file = getCookieFile();

	if(file_exists($cookie_file)) {
		$login_cookie = file_get_contents($cookie_file);
	}

	return $login_cookie;
}

function getCookieFile() {
	return dirname(__file__).'/login_cookie';
}