<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 14:28
 */
namespace App\Tool;

class RoomLogCount
{

    // 获取某个用户所有未读消息数量
    public static function getUnreadMsgCount($user_type , $user_id){
        $rooms = Room::getRooms($user_type , $user_id);

        $count = 0;

        foreach ($rooms as $v)
        {
            $_count = self::getMsgCount($v['id'] , $user_type , $user_id);
            $count += $_count;
        }

        return $count;
    }

    // 批量更新：聊天室其他群组成员未读消息数量 +1
    public static function incrMsgCount($room_id , $user_type , $user_id){
        // 获取聊天室所有成员
        $room_users = RoomUser::getRoomUser($room_id);
        $room_users = $room_users['user'];

        foreach ($room_users as $v)
        {
            if ($v['user_type'] == $user_type && $v['user_id'] == $user_id) {
                // 自己发送的自己的未读消息数量不增加
                continue ;
            }

            $count = self::getMsgCount($v['room_id'] , $v['user_type'] , $v['user_id']);
            $count++;

            // 增加未对消息数量
            self::setMsgCount($v['room_id'] , $v['user_type'] , $v['user_id'] , $count);
        }
    }


    // 获取指定房间用户未读消息数量
    public static function getRoomLogCount($room_id , $user_type , $user_id){
        $kv = md5("{$room_id}_{$user_type}_{$user_id}");
        $k_for_room_log_count = "room_log_count_{$kv}";

        if (!redis()->exists($k_for_room_log_count)) {
            $count = \DB::table('room_log_count')->where([
                ['room_id' , '=' , $room_id] ,
                ['user_type' , '=' , $user_type] ,
                ['user_id' , '=' , $user_id] ,
            ])->get()->first()->value('count');

            $data = [
                'room_id'   => $room_id ,
                'user_type' => $user_type ,
                'user_id'   => $user_id ,
                'count'     => $count
            ];

            $json = json_encode($data);

            redis()->set($k_for_room_log_count , $json);
        }

        $json = redis()->get($k_for_room_log_count);
        $data = json_decode($json , true);

        return $data;
    }

    // 获取未读消息数量
    public static function getMsgCount($room_id , $user_type , $user_id){
        $room_log_count = self::getRoomLogCount($room_id , $user_type , $user_id);
        return $room_log_count['count'];
    }

    // 设置指定用户的某个房间的消息为已读
    public static function emptyMsgCount($room_id , $user_type , $user_id)
    {
        self::setMsgCount($room_id, $user_type, $user_id, 0);
    }

    // 设置房间未读消息数量
    public static function setMsgCount($room_id , $user_type , $user_id , $count = 0){
        $kv = md5("{$room_id}_{$user_type}_{$user_id}");
        $k_for_room_log_count = "room_log_count_{$kv}";
        $limit = app_config('page.persist');

        // 设置 redis 缓存中的用户未读消息数量 --- start
        $room_log_count = self::getRoomLogCount($room_id , $user_type , $user_id);
        $room_log_count['count'] = $count;

        redis()->set($k_for_room_log_count , json_encode($room_log_count));
        // 设置 redis 缓存中的用户未读消息数量 --- end

        // 当数据超过一定数量的时候进行持久化处理 --- start
        $k_for_chat = "chat_{$room_id}";
        if (redis()->exists($k_for_chat)) {
            $history    = redis()->lRange($k_for_chat , 0 , -1);
            $count      = count($history);

            if ($count >= $limit) {
                \DB::table('room_log_count')->where([
                    ['room_id' , '=' , $room_id] ,
                    ['user_type' , '=' , $user_type] ,
                    ['user_id' , '=' , $user_id]
                ])->update([
                    'count' => $room_log_count['count']
                ]);
            }
        }
        // 当数据超过一定数量的时候进行持久化处理 --- end
    }


    // mysql 新增 room_log_count 记录
    public static function insertRoomLogCount($room_id , $user_type , $user_id){
        $count = \DB::table('room_log_count')->where([
            ['room_id' , '=' , $room_id] ,
            ['user_type' , '=' , $user_type] ,
            ['user_id' , '=' , $user_id] ,
        ])->count();

        if ($count > 0) {
            // 表示已经存在记录，无需重复添加
            return [
                'status' => true ,
                'msg' => '已经存在记录，无需重复添加'
            ];
        }

        try {
            // 添加到数据库
            \DB::table('room_log_count')->insert([
                'room_id'   => $room_id ,
                'user_type' => $user_type ,
                'user_id'   => $user_id ,
                'count'     => 0
            ]);

            return [
                'status' => true ,
                'msg' => 'pass'
            ];
        } catch(\Exception $excep) {
            return [
                'status' => false ,
                'msg' => '添加聊天室未读记录失败，请联系开发人员'
            ];
        }
    }
}