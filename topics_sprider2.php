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

worker();

function worker () {
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

				while(true) {
					sprider_topic();
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

function sprider_topic() {
	$dbh = get_dbh();

	$tid = get_topic_queue();
	$progress_id = posix_getpid();

	$time = time();

	$dbh->update('topic_index', array('index_uptime'=>$time, 'index_progress_id'=>$progress_id), array('id' => $tid));


	crawl_topic($tid);
}

function get_topic_queue($count = 10000){
	$dbh = get_dbh();
	$redis = get_redis();

	$redis_key = 'zhihu_topic_queue';

    // 如果队列为空, 从数据库取一些
    if (!$redis->lsize($redis_key)) {
        $sql = "Select `id`, `index_uptime` From `topic_index` Order By `index_uptime` Asc Limit {$count}";
        $result = $dbh->query($sql);
        $rows = $dbh->fetch_all($result);

        foreach ($rows as $row) {
            $redis->lpush($redis_key, $row['id']);
        }
    }
    // 从队列中取出一条数据
    return $redis->lpop($redis_key);
}

function crawl_topic($tid) {
	global $http;

	$url = 'https://www.zhihu.com/topic/'.$tid .'/top-answers';

	$http->get($url, function($body, $headers, $http) {
        global $dom;

        $topics = array();

		$html = $dom->load($body);

		$parent_topic = $html->find('.parent-topic div', 0)->children();

		$child_topic = $html->find('.child-topic div', 0)->children();
		
		foreach ($parent_topic as $key => $value) {
			$tid = $value->getAttribute('data-token');
			$name = trim($value->text());

			$topics[] = array(
				'id' => addslashes($tid),
	        	'name' => addslashes($name)
			);
		}

		foreach ($child_topic as $key => $value) {
			$tid = $value->getAttribute('data-token');
			$name = trim($value->text());

			$topics[] = array(
				'id' => addslashes($tid),
	        	'name' => addslashes($name)
			);
		}

		foreach ($topics as $key => $value) {
			save_topic_info($value);
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

function get_redis() {
	static $instances = array();
	$key = getmypid();
	if (empty($instances[$key])){
		$instances[$key] = new Redis();
		$instances[$key]->connect('127.0.0.1', '6379');
	}
	return $instances[$key];
}