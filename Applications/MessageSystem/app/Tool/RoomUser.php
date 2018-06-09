<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 14:28
 */
namespace App\Tool;

class RoomUser
{
// 获取聊天室成员（线上、线下）
    public static function getRoomUser($room_id){
        $users = \DB::table('room_user')->where('room_id' , $room_id)->get()->fetch();
        $online = 0;

        foreach ($users as &$v)
        {
            // 获取用户在线的平台
            $v['platform'] = Connection::getPlatforms($v['user_type'] , $v['user_id']);

            // 用户状态
            $v['status']            = Connection::isOnline($v['user_type'] , $v['user_id']) ? 'on' : 'off';
            $v['status_explain']    = get_explain('business.online_status' , $v['status']);

            if ($v['status'] == 'on') {
                $online++;
            }

            // 获取用户简略信息
            $user = User::getUser($v['user_type'] , $v['user_id']);
            $v = array_merge($v , [
                'username' => $user['username'] ?? '' ,
                'thumb' => $user['thumb'] ?? ''
            ]);
        }

        $res = [
            'room_id' => $room_id ,
            'online' => $online ,
            'count' => count($users) ,
            'user' => $users
        ];

        return $res;
    }

    // 获取聊天室在线用户数
    public static function getInfoForRoomUser($room_id){
        $users = \DB::table('room_user')->where('room_id' , $room_id)->get()->fetch();
        $online = 0;

        foreach ($users as &$v)
        {
            if (Connection::isOnline($v['user_type'] , $v['user_id'])) {
                $online++;
            }
        }

        return [
            'online' => $online ,
            'count'  => count($users)
        ];
    }

    // 获取给定聊天室在线成员
    public static function getOnlineRoomUser($room_id){
        // 聊天室在线成员
        $k = "room_user_{$room_id}";

        if (redis()->exists($k)) {
            return redis()->lRange($k , 0 , -1);
        }

        return [];
    }


    // 获取用户聊天室信息
    public static function getRoomUserInfo($room_id , $user_type , $user_id){
        $kv = md5("{$room_id}_{$user_type}_{$user_id}");
        $k_for_room_user_info = "room_user_info_{$kv}";

        if (redis()->exists($k_for_room_user_info)) {
            $json = redis()->get($k_for_room_user_info);
            $room_user_info = json_decode($json , true);
        } else {
            $room_user = \DB::table('room_user')->where([
                ['room_id' , '=' , $room_id] ,
                ['user_type' , '=' , $user_type] ,
                ['user_id' , '=' , $user_id]
            ])->find();

            // 缓存到 redis
            $room_user_info = [
                'room_id'   => $room_user['room_id'] ,
                'user_type' => $room_user['user_type'] ,
                'user_id'   => $room_user['user_id'] ,
                'tip'       => $room_user['tip'] ,
                'sort'      => $room_user['sort']
            ];

            $json = json_encode($room_user_info);

            redis()->set($k_for_room_user_info , $json);
        }

        return $room_user_info;
    }


    // 设置用户房间消息提醒状态
    public static function setRoomUserTip($room_id , $user_type , $user_id , $tip){
        $kv = md5("{$room_id}_{$user_type}_{$user_id}");
        $k_for_room_user_info = "room_user_info_{$kv}";

        $data = [
            'room_id'   => $room_id ,
            'user_type' => $user_type ,
            'user_id'   => $user_id ,
            'tip'       => $tip
        ];

        $json = json_encode($data);

        // 设置 redis 状态
        redis()->set($k_for_room_user_info , $json);

        // 更新 mysql
        \DB::table('room_user')->where([
            ['room_id' , '=' , $room_id] ,
            ['user_type' , '=' , $user_type] ,
            ['user_id' , '=' , $user_id] ,
        ])->update([
            'tip' => $tip
        ]);
    }

    // 获取聊天室用户设置（tip）
    public static function getRoomUserTip($room_id , $user_type , $user_id){
        $room_user_info = self::getRoomUserInfo($room_id , $user_type , $user_id);
        return $room_user_info['tip'];
    }

    // 数据库增信聊天室成员记录
    public static function insertRoomUser($room_id , $user_type , $user_id){
        $count = \DB::table('room_user')->where([
            ['room_id' , '=' , $room_id] ,
            ['user_type' , '=' , $user_type] ,
            ['user_id' , '=' , $user_id]
        ])->count();

        if ($count > 0) {
            // 已经存在该用户,跳过
            return ;
        }

        \DB::transaction(function() use($room_id , $user_type , $user_id){
            $max_sort = \DB::table('room_user')->where([
                ['user_type' , '=' , $user_type] ,
                ['user_id' , '=' , $user_id] ,
                ['top' , '!=' , 1]
            ])->max('sort');

            $max_sort = intval($max_sort);
            $max_sort++;

            // 添加到数据库
            \DB::table('room_user')->insert([
                'room_id' => $room_id ,
                'user_type' => $user_type ,
                'user_id' => $user_id ,
                'sort' => $max_sort ,
                'top' => 0
            ]);
        });
    }
}