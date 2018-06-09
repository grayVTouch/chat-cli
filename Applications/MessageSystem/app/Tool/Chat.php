<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 14:27
 */

namespace App\Tool;

class Chat
{
// 获取给定聊天室的聊天记录
    public static function getOnlineChat($room_id){
        $k = "chat_{$room_id}";

        if (redis()->exists($k)) {
            return redis()->lrange($k , 0 , -1);
        }

        return [];
    }

    // 获取某个聊天室的所有记录数
    // redis 记录数 + mysql 记录数
    public static function getCountForRoom($room_id){
        $online_chat = self::getOnlineChat($room_id);
        $count = count($online_chat);

        $count += \DB::table('chat')->where('room_id' , $room_id)->count();
        return $count;
    }

    // 获取给定聊天室最近一条消息
    public static function lastChat($room_id){
        $k_for_chat = "chat_{$room_id}";

        if (redis()->exists($k_for_chat)) {
            $last_chat = redis()->lRange($k_for_chat , 0 , -1);

            return json_decode($last_chat[count($last_chat) - 1] , true);
        }

        // 从数据库中获取
        $last_chat = \DB::table('chat')->where('room_id' , $room_id)->orderBy('id' , 'desc')->limit(1)->find();

        return $last_chat;
    }

    // 获取聊天室最近消息记录
    // 消息记录倒序排序（id）
    public static function getHistory($room_id){
        $limit = app_config('page.limit');

        // 获取聊天室数据 ---- start
        $k_for_chat = "chat_{$room_id}";

        if (!redis()->exists($k_for_chat)) {
            $history = \DB::table('chat')->where('room_id' , $room_id)->limit($limit)->orderBy('id' , 'desc')->get()->fetch();

            foreach ($history as &$v)
            {
                $user = User::getUser($v['user_type'] , $v['user_id']);

                $v = array_merge($v , [
                    'username' => $user['username'] ?? '' ,
                    'thumb' => $user['thumb'] ?? ''
                ]);

                // 缓存到 redis
                redis()->rPush($k_for_chat , json_encode($v));
            }
        } else {
            $history = redis()->lRange($k_for_chat , 0 , -1);

            foreach ($history as &$v)
            {
                $v = json_decode($v , true);
            }
        }

        return $history;
    }
}