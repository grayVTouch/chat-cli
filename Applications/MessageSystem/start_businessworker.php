<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use \Workerman\Worker;
// use \Workerman\WebServer;
// use \GatewayWorker\Gateway;
use \GatewayWorker\BusinessWorker;
// use \Workerman\Autoloader;
// use Core\Lib\Database;

use Workerman\Lib\Timer;
use App\Tool\TimerEvent;

// bussinessWorker 进程
$worker = new BusinessWorker();
// worker名称
$worker->name = app_config('app.system');
// bussinessWorker进程数量
$worker->count = app_config('app.count');
// 服务注册地址
$worker->registerAddress = app_config('app.register');

$worker->onWorkerStart = function(){
    // 为每个进程开启一个定时器
    Timer::add(1 , [TimerEvent::class , 'listen'] , [] , true);

    // 为每个进程进行程序的初始化
    // 一定要单独为每个进程进行初始化
    // 否则会出现数据库 或 文件继承自父进程下的资源
    // 导致某个进程关闭或退出，其他所有的进程都无法访问
    app()->run();
};

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START')) {
    Worker::runAll();
}

