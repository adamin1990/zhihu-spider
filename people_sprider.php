#!/usr/bin/php
<?php
/**
 * 根据搜索提示，抓取知乎用户
 *
 * @see  https://www.zhihu.com/r/search?q=a&type=people&offset=0
 * 
 * @author  Yang,junlong at 2016-03-17 11:07:52 build.
 * @version $Id$
 */

error_reporting(E_ALL);

if (function_exists( 'date_default_timezone_set' )){
	date_default_timezone_set('UTC');
}

require_once 'Http.class.php';
require_once 'Mysql.class.php';
require_once 'simple_html_dom.php';
require_once 'checkLogin.php';

checkLogin();

$http = new Http('http://www.zhihu.com/', array('request_headers' => array('Cookie'=>getLoginCookie())));

$dom = new simple_html_dom();

$time = time();

$kw = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$kw = str_split($kw);

// 进程数
$process_count = 8;

// 开启8个进程
for ($i = 1; $i <= $process_count; $i++) {
	try {
		$pid = pcntl_fork();
		if ($pid == -1) {
			echo "Could not fork!\n";
			exit(1);
		}
		if (!$pid) {
			$_pid = getmypid();
			echo "child process $_pid running\n";

			for ($j = 0; $j < 10000; $j++) {
			    crawl_people();
			}

			exit($_pid);
		}
	} catch(Exception $e) {
		
	}
}

while (pcntl_waitpid(0, $status) != -1) {
    $status = pcntl_wexitstatus($status);
    echo "Child $status completed\n";
}


function crawl_people() {
	$keyword = get_people_keyword();

	crawl_people_sug($keyword, 0);
}

function crawl_people_sug($keyword, $offset = 0){
	global $http;
	static $userInfo = array();
	static $userCount = 0;

	$url = 'https://www.zhihu.com/r/search?q='.$keyword.'&type=people&offset='.$offset;

	$http->get($url, function($response_body, $response_headers, $http) use (&$userInfo, &$userCount, $keyword, $offset){
		global $dom;

		$json = json_decode($response_body, true);
		$htmls = $json['htmls'];

		$user_count = count($htmls);

		if($user_count == 0) {
			return false;
		}

		foreach ($htmls as $key => $value) {
	        $html = $dom->load($value);
	        $username_ret = $html->find('.name-link', 0);

	        $href = $username_ret->href;

	        $nickname = $username_ret->text();
	        $username = substr($href, strrpos($href, '/') + 1);

	        $userInfo[$username] = array(
	        	'username' => addslashes($username),
	        	'nickname' => addslashes($nickname)
	        );

	        saveUserInfo($userInfo[$username]);
	    }

	    crawl_people_sug($keyword, $offset + 20);
    });

    return $userInfo;
}

function saveUserInfo($data) {
    $dbh = get_dbh();

    $data['ctime'] = time();

    $dbh->save('people_index', $data, array('username'=>$data['username']));

    echo "{$data['username']} success...\n";
}

function get_people_keyword () {
	global $kw;

	return array_pop($kw);
}

// util
function get_redis() {
	static $instances = array();
	$key = getmypid();
	if (empty($instances[$key])){
		$instances[$key] = new Redis();
		$instances[$key]->connect('127.0.0.1', '6379');
	}
	return $instances[$key];
}

function get_dbh() {
	static $instances = array();
	$key = getmypid();
	if (empty($instances[$key])){
		$instances[$key] = new Mysql('127.0.0.1', 'root', 'Yjl&2014', 'zhihu');
		$instances[$key]->set_char();
	}
	return $instances[$key];
}
