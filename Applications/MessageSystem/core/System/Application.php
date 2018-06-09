<?php

/**
 * *************************
 * author grayVTouch 2017-11-26
 * 应用程序
 * *************************
 */

namespace Core\System;

use Core\System\DBConnection;

class Application extends Container
{
    function __construct(){

    }

    // 绑定基础实例
    public function bindServiceProvider(){
        $mysql = app_config('app.mysql');
        $redis = app_config('app.redis');

        $mysqls = app_config("database.mysql");
        $rediss = app_config("database.redis");

        $config_for_mysql = $mysqls[$mysql];
        $config_for_redis = $rediss[$redis];

        $this->singleton('database' , new DBConnection($config_for_mysql));

        $redis = new \Redis();
        $redis->connect($config_for_redis['host'] , $config_for_redis['port']);
        $redis->auth($config_for_redis['password']);

        $this->singleton('redis' , $redis);
    }

    function run(){
        // 绑定基础服务提供者
        $this->bindServiceProvider();
    }
}