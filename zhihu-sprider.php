<?php
/**
 * 知乎 爬虫
 * 
 * @author  Yang,junlong at 2016-03-08 13:43:50 build.
 * @version $Id$
 */

require 'Http.class.php';
require 'Mysql.class.php';
require 'simple_html_dom.php';

$seeds = array('kaifulee', 'onlyswan');

$http = new Http('http://www.zhihu.com/', array('request_headers' => array('Cookie'=>'_za=f23a75a9-a490-4e94-bb53-d003b11b6004; q_c1=a95f5f370a004436b21d89cc36fc86e0|1456383631000|1451034729000; _xsrf=4eeacaa9ec8543ca5971c30f3463df6f; udid="AHCALJ0alQmPTjFOTkywYScCwUkdoDeUeW0="; cap_id="MWQ5YWUwZmI2ZWVhNDc5M2E2Y2MzZDBjZjdlMzlhMGM=|1457406822|8d27f8fb463822bc8642eec7b814b3523994104f"; z_c0="QUFBQUIyRVpBQUFYQUFBQVlRSlZUYUhSQlZjeUxtWVRsd3d2bHRsVXRUWWRBY1E5NHZWMnFBPT0=|1457407137|4532ebcd93d7218269fab4455c1b47ddb15a5605"; n_c=1; __utmt=1; __utma=51854390.2026705993.1457407273.1457417444.1457420375.4; __utmb=51854390.2.10.1457420375; __utmc=51854390; __utmz=51854390.1457420375.4.4.utmcsr=zhihu.com|utmccn=(referral)|utmcmd=referral|utmcct=/people/onlyswan/followers; __utmv=51854390.100-1|2=registration_date=20120529=1^3=entry_date=20120529=1')));


$dbh = new Mysql('127.0.0.1', 'root', '123456', 'zhihu');

$dbh->set_char();

$dom = new simple_html_dom();

function get_user_index($username, $user_type = 'followees', $worker = null) {
	global $http;

    $url = "https://www.zhihu.com/people/{$username}/{$user_type}";

    $http->get($url, function($html) use($http){
    	global $dom;

    	$html = $dom->load($html);

    	$followers_list = $html->find('.zh-general-list', 0);

    	$ajax_params = $followers_list->getAttribute('data-init');

    	$ajax_params = empty($ajax_params) ? '' : json_decode(html_entity_decode($ajax_params), true);

    	$count = count($followers_list->children());

    	$_xsrf = $html->find('input[name="_xsrf"]', 0)->value;

    	if (!empty($_xsrf) && !empty($ajax_params) && is_array($ajax_params)) {
    		$url = "http://www.zhihu.com/node/" . $ajax_params['nodename'];
    		$params = $ajax_params['params'];

    		$params['offset'] = 30;
            $post_data = array(
                'method'=>'next',
                'params'=>json_encode($params),
                '_xsrf'=>$_xsrf,
            );

            $userInfo = get_user_info($url, $post_data);
    	}
    });
}

get_user_index('liuhongissocool', 'followers');



function get_user_info($url, $data, $offset = 0){
	global $http;
	static $userInfo = array();
	static $userCount = 0;

	$params = json_decode($data['params'], true);
	$params['offset'] = $offset;
	$data['params'] = json_encode($params);

	$http->post($url, $data, function($result) use (&$userInfo, &$userCount, $url, $data, $offset){
		global $dom;

		$json = json_decode($result, true);
		$msg = $json['msg'];

		$user_count = count($msg);

		if($user_count == 0) {
			return false;
		}

		foreach ($msg as $key => $value) {
	        $html = $dom->load($value);
	        $username_ret = $html->find('.zm-list-content-title a', 0);

	        $href = $username_ret->href;

	        $nickname = $username_ret->text();
	        $username = substr($href, strrpos($href, '/') + 1);

	        $userInfo[$username] = array(
	        	'username' => $username,
	        	'nickname' => $nickname
	        );

	        saveUserInfo($userInfo[$username]);
	    }

	    echo $userCount += $user_count;

	    get_user_info($url, $data, $offset + 20);
    });

    return $userInfo;
}


function saveUserInfo($data) {
    global $dbh;

    $data['ctime'] = time();

    $dbh->save('people', $data, array('username'=>$data['username']));

    echo "{$data['username']} success...\n";
}