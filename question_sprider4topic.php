#!/usr/bin/php
<?php
/**
 * 根据话题来抓取知乎问题 索引
 * 
 * @author  Yang,junlong at 2016-03-18 13:33:26 build.
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
                    sprider_question();
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

function sprider_question() {
    $dbh = get_dbh();

    $tid = get_question_queue();

	// $sql = "Select `id`, `index_uptime` From `topic_index` WHERE  `id`=".$tid;
 //    $result = $dbh->query($sql);
 //    $rows = $dbh->fetch_all($result);

 //    if($rows && $rows[0]['index_uptime'] !=0) {
 //        return;
 //    }

    $progress_id = posix_getpid();
    $time = time();

    $dbh->update('topic_index', array('index_question_uptime'=>$time), array('id' => $tid));

    crawl_question($tid);
}

function get_question_queue($count = 10000) {
	$dbh = get_dbh();
    $redis = get_redis();

    $redis_key = 'zhihu_question4topic_queue';

    // 如果队列为空, 从数据库取一些
    if (!$redis->lsize($redis_key)) {
        //$sql = "Select `id`, `index_uptime` From `topic_index` Order By `index_uptime` Asc Limit {$count}";
        $sql = "Select `id` From `topic_index` WHERE  `index_question_uptime`=0  Limit {$count}";
        $result = $dbh->query($sql);
        $rows = $dbh->fetch_all($result);

        if(!$rows) {
            $sql = "Select `id` From `topic_index` Order By `index_question_uptime` Asc Limit {$count}";
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

function crawl_question ($tid, $page = 1) {
	global $http;
    
    $url = 'https://www.zhihu.com/topic/'.$tid .'/questions?page='.$page;

    $http->get($url, function($body, $headers, $http) use($tid, $page) {
        global $dom;

        $questions = array();

        $html = $dom->load($body);

        $questions_list = $html->find('#zh-topic-questions-list', 0);

        if(!$questions_list || count($questions_list) == 0) {
            return;
        }

        if($questions_list) {
        	$questions_child = $questions_list->children();

        	if($questions_child) {
        		foreach ($questions_child as $question_dom) {
                    $title_ret = $question_dom->find('.question-item-title a', 0);
                    $href = $title_ret->href;

                    $qid = substr($href, strrpos($href, '/') + 1);

                    $questions[] = array(
                        'id' => addslashes($qid)
                    );
                }
        	}
        }

        foreach ($questions as $value) {
            save_question_index($value);
        }

        crawl_question($tid, $page + 1);
    });
}

function save_question_index($data) {
	$dbh = get_dbh();
    $sql = "SELECT * FROM `question_index` WHERE `id`=".$data['id'];
    $dbh->query($sql);

    if(($dbh->num_results()) > 0){
		echo "{$data['id']} fail...\n";
	} else {
		$data['ctime'] = time();
		$dbh->insert('question_index', $data);

		echo "{$data['id']} success...\n";
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
