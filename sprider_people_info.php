<?php
/**
 * 抓取用户信息
 * 
 * @author  Yang,junlong at 2016-03-29 16:02:11 build.
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

if(!file_exists($moniter_name)) {
    file_put_contents($moniter_name, 0);
} else {
    $currentmodif = filemtime($moniter_name);

    if((time() - $currentmodif) < 60) {
        return;
    }
}

worker(1);

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
    $username = get_people_queue();

    $time = time();
    $dbh->update('people_index', array('info_uptime'=>$time), array('username' => $username));
    echo "sprider: {$username} start...\n";

    crawl_people($username);
}

function crawl_people($username) {
	global $http;

	$url = 'https://www.zhihu.com/people/'.$username.'/about';

	$http->get($url, function($body, $headers, $http) {
    	global $dom;
        $html = $dom->load($body);

        $profile_header = $html->find('.zm-profile-header-main', 0);

        $weibo = $profile_header->find('.zm-profile-header-user-weibo', 0);
        echo $weibo = $weibo->href;

        die();
    });
}

function get_people_queue($count = 10000) {
	$dbh = get_dbh();
    $redis = get_redis();

    $redis_key = 'zhihu_people2info_queue';

    // 如果队列为空, 从数据库取一些
    if (!$redis->lsize($redis_key)) {
        //$sql = "Select `id`, `index_uptime` From `topic_index` Order By `index_uptime` Asc Limit {$count}";
        $sql = "Select `username` From `people_index` WHERE  `info_uptime`=0  Limit {$count}";
        $result = $dbh->query($sql);
        $rows = $dbh->fetch_all($result);

        if(!$rows) {
            die();
        }

        $rows = array_reverse($rows);

        foreach ($rows as $row) {
            $redis->lpush($redis_key, $row['username']);
        }
    }
    // 从队列中取出一条数据
    return $redis->lpop($redis_key);
}

function save_people_info($data) {
	$dbh = get_dbh();
    $sql = "SELECT * FROM `people` WHERE `username`='".$data['username']."'";
    $dbh->query($sql);

    if(($dbh->num_results()) > 0){
		echo "{$data['username']} fail...\n";

		return false;
	} else {
		$data['ctime'] = time();
		$dbh->insert('people', $data);

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