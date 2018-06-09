<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/26
 * Time: 23:56
 */

return [
    // 服务器外网地址
    'server' => '127.0.0.1' ,
    // register 服务器地址
    'register' => '127.0.0.1:1238' ,
    // websocket 链接
    'websocket' => 'websocket://0.0.0.0:8282' ,
    // 默认的数据库
    'mysql' => 'vanmiao' ,
    // 默认的 redis
    'redis' => 'vanmiao' ,
    // 进程数量
    'count' => 4 ,
    // 客服一次性最多消费的临时会话
    'limit' => 1 ,
    // 系统名称
    'system' => '顽喵' ,
    // 客服名称
    'service' => '顽喵客服' ,
    // 平台分类
    'platform' => ['pc' , 'mobile' , 'app'] ,
    // 置顶权重
    'sort_for_top' => 100000 ,
    // 默认权重
    'sort_for_default' => 0
];