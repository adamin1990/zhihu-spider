#!/usr/bin/php
<?php
/**
 * 知乎 话题爬虫
 * 
 * @author  Yang,junlong at 2016-03-17 21:15:50 build.
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

$http = new Http('http://www.zhihu.com/', array('request_headers' => array('Cookie'=>getLoginCookie())));

$dom = new simple_html_dom();


function crawl_topic() {
	global $http;

	$url = 'https://www.zhihu.com/topics';

	$http->get($url, function($body, $headers, $http) {
		global $dom;

		$html = $dom->load($body);

		$topics_list = $html->find('.zh-general-list', 0);

		if(!$topics_list){
    		return;
    	}

    	$data_init = $followers_list->getAttribute('data-init');
    	$_xsrf = $html->find('input[name="_xsrf"]', 0)->value;

    	$data_init = empty($data_init) ? '' : json_decode(html_entity_decode($data_init), true);

    	if (!empty($_xsrf) && !empty($data_init) && is_array($data_init)) {
    		$url = "http://www.zhihu.com/node/" . $data_init['nodename'];
    		$params = $data_init['params'];

    		get_topic_info($url, $_xsrf, 0, $params);
    	}

	});
}

function get_topic_info($url, $_xsrf, $offset, $params) {

	$params['offset'] = $offset;

	$data = array(
		'method' => 'next',
		'params' => $params,
		'_xsrf' => $_xsrf
	);

	$http->post($url, $data, function($body, $headers, $http){
		global $dom;

		$json = json_decode($result, true);
		$msg = $json['msg'];

		$topic_count = count($msg);

		if($topic_count == 0) {
			return false;
		}

		foreach ($msg as $value) {
	        $html = $dom->load($value);
	        $topic_ele = $html->find('.blk a', 0);

	        $href = $topic_ele->href;

	        $name = $topic_ele->find('strong', 0)->text();
	        $tid = substr($href, strrpos($href, '/') + 1);

	        $topic_info = array(
	        	'id' => addslashes($tid),
	        	'name' => addslashes($name)
	        );

	        save_topic_info($topic_info);
	    }
	});
}

function save_topic_info($data) {
    $dbh = get_dbh();

    $data['ctime'] = time();

    $dbh->save('topic_index', $data, array('id'=>$data['id']));

    echo "{$data['id']} success...\n";
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