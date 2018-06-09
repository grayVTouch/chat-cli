<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 10:17
 */
namespace App\Tool;

use GatewayWorker\Lib\Gateway;
use App\System\Business;
use App\Tool\RoomUser;

class Room
{
    // 获取聊天室信息
    public static function getRoom($room_id , $user_type , $user_id){
        $rooms = self::getRooms($user_type , $user_id);

        foreach ($rooms as $v)
        {
            if ($v['id'] == $room_id) {
                return $v;
            }
        }

        return false;
    }

    // 获取聊天室排序
    public static function getRoomSort($room_id , $user_type , $user_id){
        $room_user = RoomUser::getRoomUserInfo($room_id , $user_type , $user_id);

        return intval($room_user['sort']);
    }

    // 生成正确的名称
    public static function genRoomName($room_type , $room_id , $user_type , $user_id){
        $room   = self::getRoom($room_id , $user_type , $user_id);

        // 获取聊天室发起者
        if ($room_type === 'advoise') {
            // 平台咨询
            if ($user_type === 'admin') {
                $user = User::getUser($room['user_type_for_from'] , $room['user_id_for_from']);
                // 聊天室名称 = 咨询 + 用户名
                $name = '平台咨询-' . $user['username'] ?? '尚未设置用户名';
            } else if ($user_type === 'user') {
                $name = app_config('app.service');
            } else {
                $name = $room_id;
            }
        } else if ($room_type === 'order') {
            $from   = User::getUser($room['user_type_for_from'] , $room['user_id_for_from']);
            $to     = User::getUser($room['user_type_for_to'] , $room['user_id_for_to']);

            // 检查是否是关联订单聊天室
            if ($room['is_related']) {
                return "关联订单-" . $room['name'];
            } else {
                if ($from['user_type'] == $user_type && $from['user_id'] == $user_id) {
                    // 咨询者
                    $name = $to['username'] ?? '尚未设置用户名';
                } else if ($to['user_type'] == $user_type && $to['user_id'] == $user_id) {
                    // 接受者
                    if ($from['user_type'] == 'admin') {
                        $name = '顽喵平台-' . $from['username'] ?? '尚未设置用户名';
                    } else {
                        $name = $from['username'] ?? '尚未设置用户名';
                    }
                } else {
                    // 介入者
                    $name = "争议处理-" . $room['name'];
                }
            }
        } else {
            $name = $room_id;
        }

        return $name;
    }

    // 生成聊天室 id
    public static function genRoomId($room_type , $user_type , $user_id , $order_id){
        if ($room_type === 'advoise') {
            if ($user_type !== 'user') {
                // 非前台用户不允许发起咨询！！
                return [
                    'status' => false ,
                    'msg' => '咨询仅允许普通用户发起聊天'
                ];
            }

            // 咨询, room_id = user_type + user_id
            $room_id = "user:{$user_id}";
        } else if ($room_type === 'order') {
            // 前台订单用户交流
            $order = \DB::table('order_info')->where('order_id' , $order_id)->find();

            // 检查当前发起会话的用户是否是发单人自身！如果是自身的话不允许发起会话
            if ($user_type === 'user' && $user_id == $order['send_userid']) {
                return [
                    'status'    => false ,
                    'msg'       => '不允许与自己发布的订单创建会话'
                ];
            }

            if (empty($order['accept_userid'])) {
                // 接单之前
                $room_id = "order_id:{$order_id}_{$user_type}:{$user_id}";
            } else {
                // 接单之后
                if (empty($order['tag'])) {
                    $room_id = "order_id:{$order_id}_{$user_type}:{$user_id}";
                } else {
                    $room_id = "order_id:{$order_id}_{$user_type}:{$user_id}_tag:{$order['tag']}";
                }
            }
        } else {
            return [
                'status' => false ,
                'msg' => '不支持的聊天室类型'
            ];
        }

        $room_id = md5($room_id);

        return [
            'status'    => true ,
            'msg'       => $room_id
        ];
    }

    // 仅在订单被人接下来了
    // 发起聊天的人是接单人 或 发单人
    public static function genRoomIdForOrder($user_type , $user_id , $order_id){
        $order = \DB::table('order_info')->where('order_id' , $order_id)->find();

        if ($user_type !== 'user') {
            return [
                'status' => false ,
                'msg' => '只允许普通用户发起争议处理'
            ];
        }

        if (empty($order['accept_userid'])) {
            return [
                'status' => false ,
                'msg' => '仅存在接单人的订单所在的聊天室允许发起争议'
            ];
        }

        if (($user_id != $order['send_userid'] && $user_id != $order['accept_userid'])) {
            return [
                'status' => false ,
                'msg' => '仅允许发单人或接单人发起争议'
            ];
        }

        if (empty($order['tag'])) {
            // 是否为关联订单
            $room_id = "order_id:{$order_id}_user:{$order['accept_userid']}";
        } else {
            $room_id = "user:{$order['send_userid']}_tag:{$order['tag']}";
        }

        $room_id = md5($room_id);

        return [
            'status' => true ,
            'msg' => $room_id
        ];
    }

    // 创建聊天室（待补）
    public static function createRoom($room_type , $user_type , $user_id , $order_id = null){

    }

    // 创建争议聊天室（待补）
    public static function createRoomForDisputeOrder(){

    }

    // 获取给定用户所有相关聊天室
    public static function getRooms($user_type , $user_id){
        $kv = md5("{$user_type}_{$user_id}");
        $k_for_room = "room_{$kv}";

        if (redis()->exists($k_for_room)) {
            // redis 中则直接获取
            $res = redis()->zRevRange($k_for_room, 0, -1);

            foreach ($res as &$v)
            {
                $v = json_decode($v, true);
            }
        } else {
            // redis 中为空,则从 mysql 中获取
            $res = \DB::table('room_user as ru')->innerJoin('room as r', 'ru.room_id', '=', 'r.id')->where([
                ['ru.user_type', '=', $user_type] ,
                ['ru.user_id', '=', $user_id]
            ])->select('r.*' , 'r.type as room_type' , 'r.id as room_id' , 'ru.user_type' , 'ru.user_id' , 'ru.tip' , 'ru.sort' , 'ru.top')->orderBy('ru.sort' , 'desc')->get()->fetch();

            foreach ($res as &$v)
            {
                $v = self::handleRoom($v);

                // 缓存到 redis
                redis()->zAdd($k_for_room , $v['sort'] , json_encode($v));
            }
        }

        return $res;
    }

    // 聊天室数据处理
    public static function getRoomForFull($room_id , $user_type , $user_id){
        $room   = Room::getRoom($room_id , $user_type , $user_id);
        $room['tip']    = RoomUser::getRoomUserTip($room_id , $user_type , $user_id);

        // 获取聊天室未读消息数量
        $room['count']  = RoomLogCount::getMsgCount($room_id , $user_type , $user_id);
        $room['_name']  = Room::genRoomName($room['type'] , $room['id'] , $user_type , $user_id);
        $room['sort']   = Room::getRoomSort($room['id'] , $user_type , $user_id);

        $from   = User::getUser($room['user_type_for_from'] , $room['user_id_for_from']);
        $to     = User::getUser($room['user_type_for_to'] , $room['user_id_for_to']);

        if ($room['type'] === 'advoise') {
            if ($user_type === 'admin') {
                $from   = app_config('app.service');
                $to     = $from['username'] ?? '尚未设置用户名';;
            } else {
                $from   = $from['username'] ?? '尚未设置用户名';;
                $to     = app_config('app.service');
            }
        } else if ($room['type'] === 'order') {
            if ($from['user_type'] == 'admin') {
                $from   = '顽喵平台-' . $from['username'] ?? '尚未设置用户名';
            } else {
                $from   = $from['username'] ?? '尚未设置用户名';
            }

            $to     = $to['username'] ?? '尚未设置用户名';
        } else {
            // 待补....
        }

        $room['from']   = $from;
        $room['to']     = $to;

        return $room;
    }

    // 设置指定用户聊天室置顶
    public static function topRoom($room_id , $user_type , $user_id){
        \DB::table('room_user')->where([
            ['room_id' , '=' , $room_id] ,
            ['user_type' , '=' , $user_type] ,
            ['user_id' , '=' , $user_id] ,
        ])->update([
            'top' => 1
        ]);
    }

    // 更新指定用户聊天室顺序（仅更新 redis）
    public static function updateRoomSortForRedis($room_id , $user_type , $user_id , $sort){
        // 更新 redis 聊天室顺序
        $rooms  = self::getRooms($user_type , $user_id);
        $kv     = md5("{$user_type}_{$user_id}");
        $k_for_room = "room_{$kv}";

        foreach ($rooms as $v)
        {
            // 更新 redis 中的数据
            if ($v['id'] == $room_id && $v['user_type'] == $user_type && $v['user_id'] == $user_id) {
                // 移除原有的 value
                redis()->zRem($k_for_room , json_encode($v));
                // 更新用户会话列表中的 sort
                $v['sort'] = $sort;
                $json = json_encode($v);
                redis()->zAdd($k_for_room , $sort , $json);
                break;
            }
        }
    }

    // 更新指定用户的聊天室顺序（仅更新 mysql）
    public static function updateRoomSortForMysql($room_id , $user_type , $user_id , $sort){
        \DB::table('room_user')->where([
            ['room_id' , '=' , $room_id] ,
            ['user_type' , '=' , $user_type] ,
            ['user_id' , '=' , $user_id]
        ])->update([
            'sort' => $sort
        ]);
    }

    // 聊天室加入某个成员(redis)
    public static function joinRoom($room_id , $user_type , $user_id){
        $room = self::getRoom($room_id , $user_type , $user_id);

        if ($room === false) {
            // 没有被缓存起来
            $room = \DB::table('room_user as ru')->innerJoin('room as r', 'ru.room_id', '=', 'r.id')->where([
                ['ru.room_id' , '=' , $room_id] ,
                ['ru.user_type', '=', $user_type] ,
                ['ru.user_id', '=', $user_id]
            ])->select('r.*' , 'r.type as room_type' , 'r.id as room_id' , 'ru.user_type' , 'ru.user_id' , 'ru.tip' , 'ru.sort' , 'ru.top')->find();

            // 房间处理
            $room = self::handleRoom($room);
        }

        $room_json = $room;
        $room_json = json_encode($room_json);

        // 缓存房间数据到相关用户房间列表中 in redis --- start
        $k_for_room = "room_" . md5("{$user_type}_{$user_id}");

        if (!redis()->exists($k_for_room)) {
            redis()->zAdd($k_for_room , $room['sort'] , $room_json);
        } else {
            // 按照 sort 字段进行排序!
            $rooms = redis()->zRange($k_for_room, 0, -1);
            $exists = false;

            foreach ($rooms as &$v) {
                $v = json_decode($v, true);

                if ($v['id'] == $room_id) {
                    $exists = true;
                }
            }

            // 过滤重复房间
            if (!$exists) {
                redis()->zAdd($k_for_room , $room['sort'], $room_json);
            }

        }

        // 缓存聊天室用户数据到 redis --- start
        $k_for_room_user = "room_user_{$room_id}";
        $users  = RoomUser::getOnlineRoomUser($room_id);
        $exists = false;

        foreach ($users as $v)
        {
            if ($v === "{$user_type}_{$user_id}") {
                // 如果聊天室已经存在该用户信息,则无需重复添加
                $exists = true;
            }
        }

        if (!$exists) {
            redis()->rPush($k_for_room_user , "{$user_type}_{$user_id}");
        }
        // 缓存聊天室用户数据到 redis --- start

        // 添加到线上聊天室分组
        $conns = Connection::getConns($user_type , $user_id);

        // print_r($room_id);
        // print_r($conns);
        // print_r("\n---------------\n");

        foreach ($conns as $client)
        {
            Gateway::joinGroup($client['client_id'] , $room_id);
        }
    }

    // 聊天室处理
    public static function handleRoom($room){
        // 聊天室类型中文描述
        $room['room_type_explain'] = get_explain('business.room_type' , $room['room_type']);

        // 是否关联聊天室
        $room['is_related_explain'] = get_explain('business.bool' , $room['is_related']);

        return $room;
    }
}