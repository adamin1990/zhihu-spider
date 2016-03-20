#!/usr/bin/php
<?php
/**
 * 根据话题来抓取知乎用户 索引
 * 
 * @author  Yang,junlong at 2016-03-20 14:35:26 build.
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

//checkLogin();

$http = new Http('http://www.zhihu.com/', array(
	'request_headers' => array(
		'Cookie'=>getLoginCookie()
	)
));
$dom = new simple_html_dom();

$http->setUseragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36');

$moniter_name = dirname(__file__).'/topic_moniter_people';

worker(4);

function worker ($process_count = 8) {
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

                while(true) {
                    sprider_people();
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
}

function sprider_people() {
	$dbh = get_dbh();
    $tid = get_people_queue();

    $time = time();
    $dbh->update('topic_index', array('index_question_uptime'=>$time), array('id' => $tid));
    echo "topic: {$tid} start...\n";

    crawl_people($tid, 0);
}

function crawl_people($tid, $offset) {
	global $http;

	$url = 'https://www.zhihu.com/topic/'.$tid.'/followers';

	$http->get($url, function($body, $headers, $http)  use($url, $offset) {
    	global $dom;

        $html = $dom->load($body);

        $people_list = $html->find('#zh-topic-followers-list-wrap .zm-person-item');
        $_xsrf = $html->find('input[name="_xsrf"]', 0)->value;

        if(!$people_list || count($people_list) == 0) {
            return;
        }

        if($people_list) {
        	$start_id = '';
            foreach ($people_list as $people) {
                $start_id = $people->getAttribute('id');
                $title = $people->find('.zm-list-content-title a', 0);
                $href = $title->href;

                $username = substr($href, strrpos($href, '/') + 1);
                $nickname = $title->text();

                $data = array(
	            	'username' => addslashes($username),
	        		'nickname' => addslashes($nickname)
	            );

                save_people_index($data);
            }
        }

        $start = explode('-', $start_id);
        $start = trim($start[1]);

        sprider_people2($url, $start, $offset, $_xsrf);
    });
}

function sprider_people2($url, $start, $offset, $_xsrf) {
	global $http;
	global $moniter_name;

	$data = array(
		'start' => $start,
		'offset' => $offset,
		'_xsrf' => $_xsrf
	);

	echo $url."\n";
	print_r($data);

	$http->post($url, $data, function($body, $headers, $http) use($url, $start, $offset, $_xsrf) {
		global $dom;

		$json = json_decode($body, true);

		$msg = $json['msg'];

		$qcount = $msg[0];

		$html = $dom->load($msg[1]);
		$people_list = $html->find('.zm-person-item');

		$start_id = '';
		$fail_count = 0;
		foreach ($people_list as $people) {
            $start_id = $people->getAttribute('id');
            $title = $people->find('.zm-list-content-title a', 0);
            $href = $title->href;

            $username = substr($href, strrpos($href, '/') + 1);
            $nickname = $title->text();

            $data = array(
	            'username' => addslashes($username),
	        	'nickname' => addslashes($nickname)
	        );

            if(!save_people_index($data)) {
            	$fail_count++;
            }
        }

        if($fail_count == $qcount){
        	return;
        }

        $start = explode('-', $start_id);
        $start = trim($start[1]);

        $dom->clear();
        sprider_people2($url, $start, $offset + 40, $_xsrf);
	});
}

function get_people_queue($count = 100) {
	$dbh = get_dbh();
    $redis = get_redis();

    $redis_key = 'zhihu_people4topic_queue';

    // 如果队列为空, 从数据库取一些
    if (!$redis->lsize($redis_key)) {
        //$sql = "Select `id`, `index_uptime` From `topic_index` Order By `index_uptime` Asc Limit {$count}";
        $sql = "Select `id` From `topic_index` WHERE  `index_people_uptime`=0  Limit {$count}";
        $result = $dbh->query($sql);
        $rows = $dbh->fetch_all($result);

        if(!$rows) {
            $sql = "Select `id` From `topic_index` Order By `index_people_uptime` Asc Limit {$count}";
            $result = $dbh->query($sql);
            $rows = $dbh->fetch_all($result);
        }

        $rows = array_reverse($rows);

        foreach ($rows as $row) {
            $redis->lpush($redis_key, $row['id']);
        }
    }
    // 从队列中取出一条数据
    return $redis->lpop($redis_key);
}


function save_people_index($data) {
	$dbh = get_dbh();
    $sql = "SELECT * FROM `people_index` WHERE `username`='".$data['username']."'";
    $dbh->query($sql);

    if(($dbh->num_results()) > 0){
		echo "{$data['username']} fail...\n";

		return false;
	} else {
		$data['ctime'] = time();
		$dbh->insert('people_index', $data);

		echo "{$data['username']} success...\n";
		return true;
	}
}

//util~~

function get_dbh() {
    static $instances = array();
    $key = getmypid();
    if (empty($instances[$key])){
        $instances[$key] = new Mysql('127.0.0.1', 'root', 'Yjl&2014', 'zhihu');
        $instances[$key]->set_char();
    }
    return $instances[$key];
}

function get_redis() {
    static $instances = array();
    $key = getmypid();
    if (empty($instances[$key])){
        $instances[$key] = new Redis();
        $instances[$key]->connect('127.0.0.1', '6379');
    }
    return $instances[$key];
}


function getUserAgent(){
    $useragent = array(
        'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36',
        'Baiduspider+(+http://www.baidu.com/search/spider.htm)',
        'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'
    );
}