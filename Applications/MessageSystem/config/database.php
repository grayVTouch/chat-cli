<?php

/**
 * *********************************
 * author grayVTouch 2017-11-26
 * 数据库设置
 * *********************************
 */

return [
    // MySQL连接池
    'mysql' => [
        // 默认的数据库链接
        'default' => [
            'type'       => 'mysql' ,
            'host'       => '127.0.0.1' ,
            'name'       => '' ,
            'user'       => '' ,
            'password'   => '' ,
            'prefix'     => '' ,
            'persistent' => false ,
            'charset'    => '' ,
        ] ,

        // 顽喵
        'vanmiao' => [
            'type'       => 'mysql' ,
            'host'       => '127.0.0.1' ,
            'name'       => 'vanmiao' ,
            'user'       => 'root' ,
            'password'   => '364793' ,
            'prefix'     => 'vm_' ,
            'persistent' => false ,
            'charset'    => 'utf8' ,
        ] ,
    ] ,

    // Redis 连接池
    'redis' => [
        // 默认的数据库链接
        'default' => [
            'host'      => '0.0.0.0' ,
            'port'      => 6379 ,
            'password'  => '' ,
            'timeout'   => 0
        ] ,

        // 顽喵
        'vanmiao' => [
            'host'      => '127.0.0.1' ,
            'port'      => 6379 ,
            'password'  => '364793' ,
            // 单位：s
            'timeout'   => 0
        ] ,
    ]
];