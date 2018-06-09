<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 11:42
 *
 * 获取订单信息
 */

namespace App\Tool;

class Order
{
    // 获取订单
    public static function getOrder($order_id){
        return \DB::table('order_info')->where('order_id' , $order_id)->find();
    }

    // 添加争议
    public static function addDispute($room_id , $order_id , $user_id , $title , $description = ''){
        try {
            \DB::table('order_dispute')->insert([
                'room_id' => $room_id ,
                'order_id' => $order_id ,
                'user_id' => $user_id ,
                'title' => $title ,
                'description' => $description
            ]);

            return true;
        } catch (\Exception $excep) {
            return false;
        }
    }

    // 获取指定聊天室的未解决的争议数量
    public static function getUnsolveDisputeOrderCount($room_id){
        return \DB::table('order_dispute')->where([
            ['room_id' , '=' , $room_id] ,
            ['status' , '=' , 1]
        ])->count();
    }

    // 获取聊天室正在咨询的订单
    public static function getOrderConsultation($room_id){
        $k = "order_consultation_{$room_id}";

        if (!redis()->exists($k)) {
            return false;
        }

        $lock = redis()->get($k);
        $lock = json_decode($lock , true);
        $order = self::getOrder($lock['order_id']);

        return $order;
    }
}