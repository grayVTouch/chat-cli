<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/5/10
 * Time: 10:06
 */

namespace App\Tool;

use Gatewayworker\Lib\Gateway;
use App\System\Business;

class Connection
{
    // 获取连接信息
    public static function getConn($user_type , $user_id , $platform){
        $k = "conn_{$user_type}_{$user_id}_{$platform}";

        if (redis()->exists($k)) {
            $conn = redis()->get($k);
            $conn = json_decode($conn , true);

            return $conn;
        }

        return false;
    }

    // 获取某个用户所有客户端连接id
    public static function getClientIds($user_type , $user_id){
        $conns = self::getConns($user_type , $user_id);

        foreach ($conns as $client)
        {
            $client_ids[] = $client['client_id'];
        }

        return $client_ids;
    }

    // 获取某个用户的所有相关链接信息
    public static function getConns($user_type , $user_id , $except = []){
        $keys = redis()->keys("conn_{$user_type}_{$user_id}_*_*");

        $clients = [];

        foreach ($keys as $v)
        {
            if (!redis()->exists($v)) {
                continue ;
            }

            $conn = redis()->get($v);
            $conn = json_decode($conn , true);

            // 是否要对连接的状态做判断？？
            // 如果是，那么仅返回在线用户的链接数据
            if (!Gateway::isOnline($conn['client_id'])) {
                Business::destroyUser($conn['client_id']);
                continue ;
            }

            $clients[] = $conn;
        }

        return $clients;
    }

    // 检查某个用户是否存在在线连接
    public static function isOnline($user_type , $user_id , array $client_ids = []){
        $conns = self::getConns($user_type , $user_id);

        foreach ($conns as $client)
        {
            // 如果是要求被排除的平台连接
            // 则跳过，这通常发生在销毁用户时监测用户是否还有其他在线连接
            if (in_array($client['client_id'] , $client_ids)) {
                continue ;
            }

            if (Gateway::isOnline($client['client_id'])) {
                return true;
            }
        }

        return false;
    }

    // 获取所有的后台用户连接
    public static function getAdminConns(){
        $k      = "conn_admin_*";
        $keys   = redis()->keys($k);
        $conns = [];

        foreach ($keys as $v)
        {
            $conn = redis()->get($v);
            $conn = json_decode($conn , true);

            if (!Gateway::isOnline($conn['client_id'])) {
                Business::destroyUser($conn['client_id']);
                continue ;
            }

            $conns[] = $conn;
        }

        return $conns;
    }

    // 根据链接信息对用户进行分组
    public static function groupClient($conns){
        // 去重
        $repeat = [];
        $users  = [];

        foreach ($conns as $client)
        {
            $user = "{$client['user_type']}_{$client['user_id']}";

            if (in_array($user , $repeat)) {
                continue ;
            }

            $users[] = [
                'user_type' => $client['user_type'] ,
                'user_id' => $client['user_id']
            ];
        }

        return $users;
    }

    // 获取用户在线平台
    public static function getPlatforms($user_type , $user_id){
        $platforms  = [];
        $conns      = self::getConns($user_type , $user_id);

        foreach ($conns as $client)
        {
            $platforms[] = $client['platform'];
        }

        return $platforms;
    }

    // 获取指定聊天室的所有在线用户
    public static function getOnlineForRoom($room_id){
        $users = RoomUser::getOnlineRoomUser($room_id);
        $online = [];

        foreach ($users as $v)
        {
            $user = explode('_' , $v);
            $user_type = $user[0];
            $user_id = $user[1];

            if (self::isOnline($user_type , $user_id)) {
                $online[] = [
                    'user_type' => $user_type ,
                    'user_id' => $user_id
                ];
            }
        }

        return $online;
    }

    // 获取指定聊天室的所有在线客服数量
    public static function getOnlineAdminForRoom($room_id)
    {
        $online = self::getOnlineForRoom($room_id);
        $admin_online = [];

        foreach ($online as $v)
        {
            if ($v['user_type'] === 'admin') {
                $admin_online[] = $v;
            }
        }

        return $admin_online;
    }

}