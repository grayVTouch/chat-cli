<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 11:31
 *
 * 获取聊天室发起用户
 */
namespace App\Tool;

class User
{
    // 获取用户信息
    public static function getUser($user_type , $user_id){
        if ($user_type === 'admin') {
            $user = \DB::table('admin_users')->where('id' , $user_id)->select('*' , 'name as username' , 'avatar as thumb')->find();
        } else {
            $user = \DB::table('users')->where('id' , $user_id)->select('*' , 'user_name as username')->find();
        }

        $user = self::singleHandleForUser($user);
        $user['user_type']  = $user_type;
        $user['user_id']    = $user_id;

        return $user;
    }

    // 用户数据处理：多条
    public static function multipleHandleForUser(array $data){
        foreach ($data as &$v)
        {
            $v = self::singleHandleForUser($v);
        }

        return $data;
    }

    // 用户数据处理：单条
    public static function singleHandleForUser($data){
        // 用户名称处理
        $data['username'] = $data['username'] ?? '用户尚未设置名称';
        // 用户头像处理
        $data['thumb'] = $data['thumb'] ?? app_config('res.thumb');

        return $data;
    }
}