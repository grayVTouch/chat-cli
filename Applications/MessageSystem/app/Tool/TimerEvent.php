<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 10:32
 *
 * 定时执行任务
 * 这个是用来自动分配客服使用的
 */
namespace App\Tool;

use GatewayWorker\Lib\Gateway;
use App\System\Business;
use App\Tool\Chat;
use App\Tool\Connection;

class TimerEvent
{
    // 需要执行的事件
    public static $_events = [];

    // 客服自动分配功能
    // 从客服进入某个聊天室开始对进入的聊天室进行计时
    // 当进入的时间超过限定的时候，开始统计最近一条消息发送的时间与当前时间的差值
    // 如果差值超过限定的范围，那么表明此次客服服务到此结束！
    // 客服所在的平台继续接入临时聊天室！
    public static function add($room_type , $room_id , $user_type , $user_id){
        if ($user_type !== 'admin') {
            return false;
        }

        $time = time();

        static::$_events[] = [
            'room_type' => $room_type ,
            'room_id'   => $room_id ,
            // 加入客服的 user_type
            'user_type' => $user_type ,
            // 加入客服的 user_id
            'user_id'   => $user_id ,
            // 加入客服的 时间
            'time'      => $time
        ];

    }

    // 事件监听
    public static function listen(){
        $wait_time  = app_config('time.wait_time');
        $time_out   = app_config('time.time_out');

        $time = time();

        // 要取消监听的聊天室
        $destroy = [];

        // 当前支持的平台
        // $platforms = app_config('app.platform');

        foreach (static::$_events as $k => $v)
        {
            if (!Connection::isOnline($v['user_type'] , $v['user_id'])) {
                // 处理消息的客服不在线了
                // 销毁 key
                $destroy[] = $k;
                continue ;
            }

            $d = $time - $v['time'];

            // 超时执行
            if ($d < $wait_time) {
                continue ;
            }

            // 获取最近一套消息
            $last_chat = Chat::lastChat($v['room_id']);

            if (!empty($last_chat)) {
                $last_time = $last_chat['unix_time'];

                // 当最近一条记录的发送时间已经超过给定的超时时间后
                // 认定该聊天室的客服服务已经结束了
                // 减少客服正在处理的聊天室数量
                if ($time - $last_time <= $time_out) {
                    continue ;
                }
            }

            // 1. 没有进行会话交流
            // 2. 会话超时
            // 处理：不在对该聊天室进行时长统计，销毁
            $destroy[] = $k;

            // 如果存在锁定会话，接触锁定
            if (Business::isLockService($v['room_type'] , $v['room_id'])) {
                if (Business::getLockService($v['room_type'] , $v['room_id']) == "{$v['user_type']}_{$v['user_id']}") {
                    Business::unlockService($v['room_type'] , $v['room_id']);
                }
            }

            // 减少该客服处理的数量
            $client_count = Business::serviceCount($v['user_type'] , $v['user_id']);
            $client_count--;
            Business::setServiceCount($v['user_type'] , $v['user_id'] , $client_count);

            // 向该客服新增会话数量
            $tmp_room = Business::getTmpRoom();

            if ($tmp_room) {
                // 该客服在所有平台的连接统统加入到会话中
                // 如果存在临时聊天室，加入！！
                Business::serviceJoinTemp($tmp_room['type'] , $tmp_room['id'] , $v['user_type'] , $v['user_id']);

                // 自动分配客服
                Business::allocateService($tmp_room['type'] , $tmp_room['id'] , $v['user_type'] , $v['user_id']);
            }
        }

        foreach ($destroy as $v)
        {
            // 删除已经处理的聊天室
            unset(static::$_events[$v]);
        }
    }
}