#!/usr/bin/php
<?php
/**
 * 知乎 爬虫
 * 
 * @author  Yang,junlong at 2016-03-16 21:46:50 build.
 * @version $Id$
 */

require_once 'Http.class.php';
require_once 'Mysql.class.php';
require_once 'simple_html_dom.php';

function checkLogin() {
	$login_cookie = '';
	if(file_exists('login_cookie')) {
		$login_cookie = file_get_contents('login_cookie');
	}

	$http = new Http('https://www.zhihu.com/', array('request_headers' => array('Cookie'=>$login_cookie)));
	$dom = new simple_html_dom();


	$http->get(function($html) use($http, $dom){
		$ele = $dom->load($html);

		$login_flag = $ele->find('.sign-button', 0);
		$_xsrf = $ele->find('input[name="_xsrf"]', 0)->value;

		if($login_flag) {
			// 需要登录
			$data = array(
				'email' => 'crossyou2009@gmail.com',
				'password' => '879150',
				'remember_me' => 'true',
				'_xsrf' => $_xsrf
			);
			$http->post('https://www.zhihu.com/login/email', $data, function($response_body, $response_headers){
				print_r($response_headers);
			});
		}
	});
}

checkLogin();