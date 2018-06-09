<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/5/11
 * Time: 13:43
 */

namespace App\Tool;


class Message
{
    // 封装成功信息
    public static function success($msg_type , $callback , $msg){
        return self::format($msg_type , $callback , 'success' , $msg);
    }

    // 封装失败信息
    public static function error($msg_type , $callback , $msg){
        return self::format($msg_type , $callback , 'error' , $msg);
    }

    // 封装信息
    public static function format($msg_type , $callback , $status , $msg){
        $data = [
            'msg_type'  => $msg_type ,
            'content'   => [
                'callback'  => $callback ,
                'data'      => [
                    'status'    => $status ,
                    'msg'       => $msg
                ]
            ]
        ];
        $json = json_encode($data);
        return $json;
    }
}