<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 9:50
 */

namespace App\System;

use GatewayWorker\Lib\Gateway;
use App\Tool\Chat;
use App\Tool\Room;
use App\Tool\RoomUser;
use App\Tool\TimerEvent;
use App\Tool\RoomLogCount;
use App\Tool\Order;
use App\Tool\User;
use Core\System\Encryption;
use App\Tool\Connection;
use App\Tool\Message;

class Business
{
    // redis 用到的所有 key
    // 仅做记录查找方便用
    public static $keys = [
        // 在线用户
        'conn_user_md5({$user_type}_{$user_id})' ,
        // 待补 ...
    ];

    // 通知对方关闭 socket 链接
    public static function close($client_id , $msg){
        $json = Message::success('close' , '' , $msg);

        // 通知客户端关闭链接
        Gateway::sendToClient($client_id , $json);

        // 服务器也关闭链接
        Gateway::closeClient($client_id);
    }

    // 连接注册
    public static function login($client_id , $type , $data){
        // 这边需要对用户的连接信息做一个检查
        // ....

        $user_type  = Encryption::decrypt($data['user_type']);
        $user_id    = Encryption::decrypt($data['user_id']);

        // 解析失败，通知对方关闭链接
        if (!$user_type || !$user_id) {
            self::close($client_id , '用户身份认证失败，拒绝连接');
            return ;
        }

        // 检查对应平台是否已经存在平台
        // 如果已经存在用户登录，则下线
        $k_for_conns = "conn_{$user_type}_{$user_id}_{$data['platform']}_*";
        $v_for_conns = redis()->keys($k_for_conns);

        // 单个平台一个账号有多个登录！
        // 通知他们下线
        if (count($v_for_conns) > 0) {
            /*
             * 有需要时开放
             * 暂时不开发，如果开发，会导致用户在单个平台仅能登陆一次！
             * 例如后台用户就会出问题，后台用户首先登陆后台
             * 其次登陆聊天系统，这就产生了两次登陆
             * 如果开启这边的功能，这是不被允许的
            foreach ($v_for_conns as $v)
            {
                $client = redis()->get($v);
                $client = json_decode($client , true);

                if (Gateway::isOnline($client['client_id'])) {
                    self::close($client['client_id'] , '你的账号已经在其他地方登录！请退出当前登录！');
                }

                // 销毁用户信息
                redis()->del($v);
            }
            */
        }

        // 缓存用户信息 --- start
        $k_for_user_info = "user_info_{$user_type}_{$user_id}";

        if (!redis()->exists($k_for_user_info)) {
            $user = User::getUser($user_type , $user_id);

            // 注册
            $user_info = json_encode([
                // 未加密数据
                'user_type' => $user_type ,
                // 未加密数据
                'user_id'   => $user_id ,
                // 加密数据
                '_user_type' => $data['user_type'] ,
                // 加密数据
                '_user_id' => $data['user_id'] ,
                'username'  => $user['username'] ,
                'thumb'     => $user['thumb']
            ]);

            redis()->set($k_for_user_info , $user_info);
        }
        // 缓存用户信息 --- end

        // ---- 注册用户的连接信息 start
        $connect = [
            // 解密后数据
            'user_type'     => $user_type ,
            'user_id'       => $user_id ,
            // 平台：pc | app
            'platform'      => $data['platform'] ,
            // 客户端 id
            'client_id'     => $client_id ,
        ];

        $kv = "{$user_type}_{$user_id}_{$data['platform']}_{$client_id}";
        $k_for_conn = "conn_{$kv}";
        $v_for_conn = json_encode($connect);

        redis()->set($k_for_conn , $v_for_conn);
        // ---- 注册用户的连接信息 end

        // ---- 将 client_id 与 user_type + user_id 关联起来 start
        // ---- 这么做是为了在用户断开链接的时候,可以及时销毁对应的用户连接数据
        $k_for_client = "client_{$client_id}";
        $v_for_client = $kv;

        redis()->set($k_for_client , $v_for_client);
        // ---- 将 client_id 与 user_type + user_id 关联起来 end

        // 用户上线后，加入到相关聊天室
        self::joinRelatedRoom($user_type , $user_id);

        // 如果是后台用户，检查是否有临时消息未被读取
        if ($user_type === 'admin') {
            // 重置客服的链接数量
            // 这种做法是错误的！！
            // 如果客服在其他平台登录
            // 那么现有平台即使已经有接待的客服了
            // 该客服的负载也会被重置
            // 这显然不行
            // 如果除当前连接之外无其他连接，那么重置负载！！
            if (!Connection::isOnline($user_type , $user_id , [$client_id])) {
                self::setServiceCount($user_type , $user_id , 0);
            }

            // 仅后台用户需要读取临时消息
            // 每个后台用户仅最多读取20个临时会话
            // 不然一次性读取所有的临时会话不太现实
            self::consumeTmp($user_type , $user_id);
        }

        // 反馈注册成功提示
        $json = Message::success($type , $data['callback'] , [
            'user_type' => $user_type ,
            'user_id' => $user_id ,
            'client_id' => $client_id
        ]);

        Gateway::sendToClient($client_id , $json);

        // 最近历史记录同步
        $history = self::history($user_type , $user_id);
        $json = Message::success('history' , '' , $history);
        Gateway::sendToClient($client_id , $json);

        // 通知所有相关聊天室，当前登录用户上线了！！
        self::notification($data['platform'] , $user_type , $user_id , 'on');
    }

    // 聊天室成员同步
    public static function user($client_id , $type , $data){
        // 手动获取聊天室成员（线上、线下）
        $users = RoomUser::getRoomUser($data['room_id']);
        $json = Message::success($type , $data['callback'] , $users);
        Gateway::sendToClient($client_id , $json);
    }

    // 单个聊天室记录同步
    public static function syn($client_id , $type , $data){
        // 手动同步聊天室信息
        $room = Room::getRoomForFull($data['room_id'] , $data['user_type'] , $data['user_id']);
        $room['history']    = Chat::getHistory($data['room_id']);
        $room['user']       = RoomUser::getRoomUser($data['room_id']);
        $json = Message::success($type , $data['callback'] , $room);
        Gateway::sendToClient($client_id , $json);
    }

    // 某个用户上线后
    // 加入到相关聊天室
    public static function joinRelatedRoom($user_type , $user_id){
        $rooms = Room::getRooms($user_type , $user_id);

        foreach ($rooms as $v)
        {
            Room::joinRoom($v['id'] , $user_type , $user_id , $v['sort']);
        }
    }

    // 读取临时会话（仅读取空方间）
    // 这边的空方间意味着是需要客服介入的空方间
    // 会产生空间的情况：平台咨询的时候
    // 单个客服一次性最多加入 20 个会话
    // 否则就太多了！会应付不过来
    // 单个单个增加
    public static function consumeTmp($user_type , $user_id){
        $limit = app_config('app.limit');
        $tmp_rooms = redis()->keys("tmp_room_*");
        $count = count($tmp_rooms);
        $count = $count >= $limit ? $limit : $count;

        for ($i = 0; $i < $count; ++$i)
        {
            $k_for_tmp_room = $tmp_rooms[$i];
            $v_for_tmp_room = redis()->get($k_for_tmp_room);
            $v_for_tmp_room = json_decode($v_for_tmp_room , true);

            // 客服加入到某个临时聊天室中
            self::serviceJoinTemp($v_for_tmp_room['type'] , $v_for_tmp_room['id'] , $user_type , $user_id);
        }
    }

    // 将某个客服加入到某个聊天室
    public static function serviceJoinTemp($room_type , $room_id , $user_type , $user_id){
        // 非后台客服不具备处理临时会话的能力
        if ($user_type !== 'admin') {
            var_dump('非后台客服不具备处理临时会话的能力');
            return false;
        }

        // 插入到 mysql 数据库，持久话处理（必须先添加！）
        RoomUser::insertRoomUser($room_id , $user_type , $user_id);

        // 加入到聊天室
        Room::joinRoom($room_id , $user_type , $user_id);

        // 通知所有在线的该聊天室的其他成员新增成员
        self::addUser($room_id , $user_type , $user_id);

        // 添加未读消息记录
        RoomLogCount::insertRoomLogCount($room_id , $user_type , $user_id);

        // 获取给定聊天室所有聊天记录
        $unread_count = Chat::getCountForRoom($room_id);

        // 设置未读消息数量
        RoomLogCount::setMsgCount($room_id , $user_type , $user_id , $unread_count);

        // 锁定会话
        self::lockService($room_type , $room_id , $user_type , $user_id);

        // 一旦客服加入后，该临时 key 就应该被销毁
        $k_for_tmp_room = "tmp_room_{$room_id}";
        redis()->del($k_for_tmp_room);

        // 开始对会话进行计时
        // 超过 5min 后，读取最近一条记录的创建时间与当前时间的差值
        // 如果同样超过 5 min 那么继续消费临时会话！！
        // 具体配置请在 timer.php 中查看 & 设置
        TimerEvent::add($room_type , $room_id , $user_type , $user_id);
    }

    // 新增成员通知
    public static function addUser($room_id , $user_type , $user_id){
        $info = RoomUser::getInfoForRoomUser($room_id);
        $user = self::getUser($user_type , $user_id);
        $status = Connection::isOnline($user_type , $user_id) ? 'on' : 'off';
        $status_explain = get_explain('business.online_status' , $status);

        $send = array_merge($info , $user , [
            'room_id'   => $room_id ,
            'status'    => $status ,
            'status_explain' => $status_explain
        ]);

        $json = Message::success('add_user' , '' , $send);
        Gateway::sendToGroup($room_id , $json);
    }

    // 作为 api：自动分配在线客服
    public static function autoAllocateAsAPI($client_id , $type , $data){
        // 自动分配在线客服
        $res = self::autoAllocate($data['room_type'] , $data['room_id']);
        $status = $res ? 'success' : 'error';
        $msg    = $res ? '自动分配客服成功' : '自动分配客服失败';

        $json = Message::format($type , $data['callback'] , $status , $msg);
        Gateway::sendToClient($client_id , $json);
    }

    // 加入聊天室
    public static function joinRoom($client_id , $type , $data){
        // 加入聊天室
        foreach ($data['users'] as $v)
        {
            // 先添加到 mysql
            RoomUser::insertRoomUser($data['room_id'] , $v['user_type'] , $v['user_id']);

            // 用户加入到聊天室并缓存相关数据到 redis
            Room::joinRoom($data['room_id'] , $v['user_type'] , $v['user_id']);

            // 说明聊天室中加入了非当前连接用户
            // 需要通知该用户它被加入到了该聊天室
            $room   = Room::getRoomForFull($data['room_id'] , $v['user_type'] , $v['user_id']);

            $room['history']    = Chat::getHistory($data['room_id']);
            $room['user']       = RoomUser::getRoomUser($data['room_id']);

            // 通知已加入成员相关连接产生新的会话
            // 将每个用户的所有相关连接都加入到聊天室
            $clients = Connection::getConns($v['user_type'] , $v['user_id']);
            foreach ($clients as $client)
            {
                $send = Message::success('session' , '' , $room);
                Gateway::sendToClient($client['client_id'] , $send);
            }
        }

        $send = Message::success($type , $data['callback'] , '加入聊天室成功');
        Gateway::sendToClient($client_id , $send);
    }

    // 设置指定用户房间的消息为已读
    public static function emptyMsgCount($client_id , $type , $data){
        // 更新某个聊天室成员未读消息数量
        RoomLogCount::emptyMsgCount($data['room_id'] , $data['user_type'] , $data['user_id']);

        $json = json_encode([
            'msg_type' => 'empty_msg_count' ,
            'content' => [
                'callback' => $data['callback'] ,
                'data' => [
                    'status' => 'success' ,
                    'msg'    => '设置未读消息数量成功'
                ]
            ]
        ]);
        Gateway::sendToClient($client_id , $json);
    }

    // 获取指定聊天室的信息
    public static function getRoom($client_id , $type , $data){
        // 获取给定房间对应给定用户时的信息
        $room = Room::getRoom($data['room_id'] , $data['user_type'] , $data['user_id']);
        $room = Room::getRoomForFull($room['id'] , $data['user_type'] , $data['user_id']);

        $room['history']    = Chat::getHistory($room['id']);
        $room['user']       = RoomUser::getRoomUser($room['id']);

        $json = json_encode([
            'msg_type' => $type ,
            'content' => [
                'callback' => $data['callback'] ,
                'data' => [
                    'status'    => 'success' ,
                    'msg'       => $room
                ]
            ]
        ]);
        Gateway::sendToClient($client_id , $json);
    }

    // 设置房间的消息提醒模式
    public static function setRoomUserTip($client_id , $type , $data){
        // 设置聊天室消息提醒状态
        RoomUser::setRoomUserTip($data['room_id'] , $data['user_type'] , $data['user_id'] , $data['tip']);

        $json = json_encode([
            'msg_type' => 'set_room_user_tip' ,
            'content' => [
                'callback' => $data['callback'] ,
                'data' => [
                    'status' => 'success' ,
                    'msg' => '设置用户房间消息状态成功'
                ]
            ]
        ]);
        Gateway::sendToClient($client_id , $json);
    }

    // 获取给定客服当前处理的客户数量（压力）
    public static function serviceCount($user_type , $user_id){
        if ($user_type !== 'admin') {
            return false;
        }

        $kv = md5("{$user_type}_{$user_id}");
        $k_for_service_count = "service_count_{$kv}";

        if (!redis()->exists($k_for_service_count)) {
            return 0;
        }

        $service_count = redis()->get($k_for_service_count);
        $service_count = intval($service_count);

        return $service_count;
    }

    // 设置客服的数量
    public static function setServiceCount($user_type , $user_id , $count = 0){
        $kv = md5("{$user_type}_{$user_id}");
        $k_for_service_count = "service_count_{$kv}";

        redis()->set($k_for_service_count , $count);
    }

    // 锁定会话
    public static function lockService($room_type , $room_id , $user_type , $user_id){
        if ($user_type !== 'admin') {
            return false;
        }

        $k_for_service_lock = "{$room_type}_service_lock_{$room_id}";
        $v_for_serivce_lock = "{$user_type}_{$user_id}";

        redis()->set($k_for_service_lock, $v_for_serivce_lock);
    }

    // 解除锁定
    public static function unlockService($room_type , $room_id){
        $k_for_service_lock = "{$room_type}_service_lock_{$room_id}";

        redis()->del($k_for_service_lock);
    }

    // 检查是否被锁定
    public static function isLockService($room_type , $room_id){
        $k_for_service_lock = "{$room_type}_service_lock_{$room_id}";

        return redis()->exists($k_for_service_lock);
    }

    // 获取被锁定的用户
    public static function getLockService($room_type , $room_id){
        $k_for_service_lock = "{$room_type}_service_lock_{$room_id}";

        return redis()->get($k_for_service_lock);
    }

    // 获取一条临时聊天室
    public static function getTmpRoom(){
        $keys = redis()->keys('tmp_room_*');

        if (count($keys) === 0) {
            return false;
        }

        $k_for_tmp_room = $keys[0];
        $tmp_room = redis()->get($k_for_tmp_room);
        $tmp_room = json_decode($tmp_room , true);

        // 销毁
        redis()->del($k_for_tmp_room);

        return $tmp_room;
    }

    // 为指定房间自动分配在线客服
    // 如果指定房间已经存在在线客服
    // 那么将不再分配新客服
    public static function autoAllocate($room_type , $room_id){
        // 获取聊天室在线客服数量
        $admin_online   = Connection::getOnlineAdminForRoom($room_id);

        // 该聊天室存在在线客服
        // 无需分配，直接返回
        if (!empty($admin_online)) {
            var_dump('该聊天室已经存在在线客服，无需再次分配');
            return ;
        }

        $admin_conns    = Connection::getAdminConns();
        $admin_users    = Connection::groupClient($admin_conns);
        $services       = [];
        $limit          = app_config('app.limit');

        foreach ($admin_users as $v)
        {
            // 获取客服目前接入的客户端数量
            $client_count = self::serviceCount($v['user_type'] , $v['user_id']);

            // 客服繁忙、无法再处理其他的客户了！！
            if ($client_count > $limit) {
                continue ;
            }

            $v['client_count'] =  $client_count;

            $services[] = $v;
        }

        if (empty($services)) {
            // 不存在在线客服
            // 保存到临时消息会话
            var_dump('不存在在线客服，会话被保存到了临时会话');
            self::saveTmpRoom($room_type , $room_id);
            return false;
        }

        // 存在空闲在线客服，随机分配
        $k          = mt_rand(0, count($services) - 1);
        $service    = $services[$k];

        // 分配客服进入聊天室
        self::allocateService($room_type , $room_id , $service['user_type'] , $service['user_id']);

        return $service;
    }

    // 分配客服！！
    public static function allocateService($room_type , $room_id , $user_type , $user_id){
        // 增加该客服目前的接待数量
        $client_count = self::serviceCount($user_type , $user_id);
        $client_count++;
        self::setServiceCount($user_type , $user_id , $client_count);

        // 添加聊天室到 mysql vm_room_user 数据库
        RoomUser::insertRoomUser($room_id, $user_type, $user_id);

        // 添加聊天室到 redis 缓存
        Room::joinRoom($room_id, $user_type, $user_id);

        // 添加未读消息记录数量
        RoomLogCount::insertRoomLogCount($room_id , $user_type , $user_id);

        // 锁定会话
        self::lockService($room_type , $room_id , $user_type , $user_id);

        // 通知对应用户新新增会话
        // 由于是运行时分配的，而不是初始化前分配的
        // 所以需要通知对应用户，新增会话！
        $room   = Room::getRoomForFull($room_id , $user_type , $user_id);
        $room['history'] = Chat::getHistory($room_id);
        $room['user'] = RoomUser::getRoomUser($room_id);

        // 通知所有加入聊天室的在线连接，新增会话
        $conns = Connection::getConns($user_type , $user_id);

        foreach ($conns as $client)
        {
            $send = array_merge($room , [
                'platform' => $client['platform']
            ]);

            $json = Message::success('session' , '' , $send);
            Gateway::sendToClient($client['client_id'] , $json);
        }

        // 通知聊天室的其他成员有新用户上线
        self::addUser($room_id , $user_type , $user_id);
    }

    // 通知聊天室成员
    public static function notification($platform , $user_type , $user_id , $status){
        // 获取用户相关的聊天室
        $rooms          = Room::getRooms($user_type , $user_id);
        $status_explain = get_explain('business.online_status' , $status);
        $real_status    = Connection::isOnline($user_type , $user_id) ? 'on' : 'off';
        $real_status_explain = get_explain('business.online_status' , $real_status);

        foreach ($rooms as $v)
        {
            $info = RoomUser::getInfoForRoomUser($v['id']);

            $send = array_merge($info , [
                'platform' => $platform ,
                'user_type' => $user_type ,
                'user_id'   => $user_id ,
                'room_type' => $v['type'] ,
                'room_id'   => $v['id'] ,
                // 用户单个连接的状态
                'status'    => $status ,
                'status_explain' => $status_explain ,
                // 该用户实际的线上状态
                'real_status' => $real_status ,
                'real_status_explain' => $real_status_explain
            ]);

            $json = Message::success('notification' , '' , $send);

            // 通知相关聊天室用户上线
            Gateway::sendToGroup($v['id'] , $json);
        }
    }

    // 同步最近消息(redis 中读取)
    // 获取与该用户相关的所有房间消息!
    public static function history($user_type , $user_id){
        $rooms = Room::getRooms($user_type , $user_id);
        $unread_msg_count = RoomLogCount::getUnreadMsgCount($user_type , $user_id);

        // ---- 获取相关历史记录 ---start
        $res = [
            'user_type' => $user_type ,
            'user_id'   => $user_id ,
            // 总的未读消息数量
            'unread_msg_count' => $unread_msg_count
        ];
        $history = [];
        foreach ($rooms as &$v)
        {
            $v = Room::getRoomForFull($v['id'] , $user_type , $user_id);

            $line = $v;

            // 历史记录
            $his = Chat::getHistory($v['id']);

            // 过滤掉没有记录的聊天室
            if (empty($his)) {
                continue ;
            }

            // 聊天室记录
            $line['history']    = $his;
            // 聊天室成员
            $line['user']       = RoomUser::getRoomUser($v['id']);
            // 获取聊天室正在咨询的订单
            if ($v['type'] === 'order') {
                $line['order'] = Order::getOrderConsultation($v['id']);
            }

            // 获取聊天室数据
            $history[] = $line;
        }

        // 所有的聊天室
        $res['room'] = $rooms;
        // 聊天记录
        $res['history'] = $history;

        return $res;
    }

    // 获取给定用户链接信息
    public static function getUser($user_type , $user_id){
        $k_for_user_info = "user_info_{$user_type}_{$user_id}";

        if (redis()->exists($k_for_user_info)) {
            $json = redis()->get($k_for_user_info);
            $json = json_decode($json , true);

            return $json;
        }

        return false;
    }

    // 获取连接信息
    public static function getConnect($platform , $user_type , $user_id , $client_id){
        $k_for_conn = "conn_{$platform}_{$user_type}_{$user_id}_{$client_id}";

        if (redis()->exists($k_for_conn)) {
            $conn = redis()->get($k_for_conn);
            $conn = json_decode($conn , true);

            return $conn;
        }

        return false;
    }

    // 数据持久化(保存到 mysql)
    public static function persistent($room_id)
    {
        $k_for_chat = "chat_{$room_id}";
        $records    = Chat::getOnlineChat($room_id);
        $count      = count($records);
        $limit      = app_config('page.persist');

        if ($count < 20) {
            return;
        }

        // 记录都是倒序排列的
        // 插入到数据库的记录必须按顺序排列
        $records = array_reverse($records);

        \DB::transaction(function() use($records){
            foreach ($records as &$v)
            {
                $v = json_decode($v, true);

                // 先检查数据是否已经存在
                // 记住，这不是程序 bug !!
                // 如果 redis 缓存中没有数据的时候，会从 mysql 中读取数据
                // 这个时候是按照满足持久化条件的记录数进行读取的
                // 所以一旦再次满足持久化条件时，便会执行插入数据库操作
                // 而这些一开始就是从 mysql 读取的数据，显然就重复了！
                // 所以会发生重复现象
                // 因而需要对数据进行判断
                $count = \DB::table('chat')->where('identifier' , $v['identifier'])->count();

                if ($count >= 1) {
                    echo "非程序bug:消息记录的唯一标识符出现重复现象！！可能的情况是将从 mysql 读取的数据重新插入会 mysql 导致的\n" ;
                    continue ;
                }

                // 数据处理
                \DB::table('chat')->insert([
                    'room_id' => $v['room_id'],
                    'user_type' => $v['user_type'] ,
                    'user_id' => $v['user_id'] ,
                    'type'  => $v['type'] ,
                    'identifier' => $v['identifier'],
                    'content' => $v['content'],
                    'create_time' => $v['create_time']
                ]);
            }
        });

        // 删除 key
        redis()->del($k_for_chat);
    }

    // 用户发送消息时，推送给后台用户未读消息数量
    // 比如，A 用户在聊天室 C 中发送了一条消息，那么该聊天室所有相关用户的未读消息数量都需要更新
    public static function pushToAdmin($client_id){
        $admin_user_keys = redis()->keys('conn_admin_*');
        $online_admin_users = [];
        foreach ($admin_user_keys as $v)
        {
            $admin_user = redis()->get($v);
            $admin_user = json_decode($admin_user , true);

            if (!Gateway::isOnline($admin_user['client_id'])) {
                self::destroyUser($admin_user['client_id']);
                continue ;
            }

            // 获取该用户的未读消息数量
            $admin_user['unread_msg_count'] = RoomLogCount::getUnreadMsgCount($admin_user['user_type'] , $admin_user['user_id']);

            $online_admin_users[] = $admin_user;
        }

        foreach ($online_admin_users as $v)
        {
            $json = Message::success('unread_msg_count' , '' , $v['unread_msg_count']);
            Gateway::sendToClient($v['client_id'] , $json);
        }
    }

    // 推送给所有相关用户
    public static function pushToRelated($room_id){
        $online = Connection::getOnlineForRoom($room_id);

        foreach ($online as $v)
        {
            // 总的消息未读数量
            $unread_msg_count = RoomLogCount::getUnreadMsgCount($v['user_type'] , $v['user_id']);

            // 通知相关客户端连接
            $conns = Connection::getConns($v['user_type'] , $v['user_id']);

            foreach ($conns as $v1)
            {
                $json = Message::success('unread_msg_count' , '' , $unread_msg_count);
                Gateway::sendToClient($v1['client_id'] , $json);
            }
        }
    }

    // 向聊天室发送消息
    public static function send($type , $data){
        // 获取消息源连接信息
        $user = self::getUser($data['user_type'] , $data['user_id']);

        // 如果没有当前用户连接信息,则 pass
        if ($user === false) {
            return ;
        }

        // 聊天室群组成员的未读消息数量 +1
        RoomLogCount::incrMsgCount($data['room_id'] , $data['user_type'] , $data['user_id']);

        $callback = $data['callback'];

        unset($data['callback']);

        $time = time();

        $send_data = $data;
        $send_data['username']      = $user['username'];
        $send_data['thumb']         = $user['thumb'];
        $send_data['create_time']   = date('Y-m-d H:i:s' , $time);
        $send_data['unix_time']     = $time;
        // 获取该用户当前聊天室未读消息数量
        $send_data['unread_msg_count'] = RoomLogCount::getMsgCount($data['room_id'] , $data['user_type'] , $data['user_id']);


        // 保存用户信息 --- start
        $k_for_chat = "chat_{$data['room_id']}";

        // 保存消息到 redis
        redis()->lPush($k_for_chat , json_encode($send_data));
        // 保存用户信息 --- start end

        $json = Message::success($type , $callback , $send_data);

        // 发送消息
        Gateway::sendToGroup($data['room_id'] , $json);
    }

    // 没人理会的房间（冷宫）
    // 临时保存，让客服使他们活跃起来
    public static function saveTmpRoom($room_type , $room_id){
        // 这边这个 key 实际仅对应 advoise 平台咨询
        $k_for_tmp_room = "tmp_room_{$room_id}";

        $v_for_tmp_room = [
            'id' => $room_id ,
            'type' => $room_type
        ];
        $v_for_tmp_room = json_encode($v_for_tmp_room);

        // 添加到数据中
        redis()->set($k_for_tmp_room , $v_for_tmp_room);
    }

    // 为每一个消息生成一个标识符
    // 无论该消息在 mysql 还是 redis
    // 都能够精确的找到该消息
    public static function genCode(){
        return implode(random(256 , 'mixed'));
    }

    // 平台咨询,自动分配在线客服,如果都下线了,则临时保存到 redis 中
    // 用户在进行平台咨询之前,需要先生成聊天室
    public static function advoise($client_id , $type , $data){
        // 为该消息生成唯一 id
        $data['identifier'] = self::genCode();

        // 加入到聊天室（这个不会重复添加）
        Room::joinRoom($data['room_id'] , $data['user_type'] , $data['user_id']);

        if ($data['user_type'] === 'admin') {
            $k_for_advoise_service_lock = "advoise_service_lock_{$data['room_id']}";

            if (redis()->exists($k_for_advoise_service_lock)) {
                $v_for_advoise_serivce_lock = redis()->get($k_for_advoise_service_lock);

                if ($v_for_advoise_serivce_lock !== "{$data['user_type']}_{$data['user_id']}") {
                    // 如果存在
                    $json = Message::error($type , $data['callback'] , '该用户的咨询已经有客服在处理了');
                    Gateway::sendToClient($client_id , $json);
                    return ;
                }
            } else {
                redis()->set($k_for_advoise_service_lock , "{$data['user_type']}_{$data['user_id']}");
            }

            // 发送消息
            self::send($type , $data);
            self::persistent($data['room_id']);
            // self::pushToAdmin($client_id);
            self::pushToRelated($client_id);
        } else {
            // 获取在线聊天室成员
            $users = RoomUser::getOnlineRoomUser($data['room_id']);

            // ----检查是否存在客服 start
            // 是否存在在线后台客服
            $exists_online_servcie = false;

            foreach ($users as $v)
            {
                $line       = explode('_' , $v);
                $user_type  = $line[0];
                $user_id    = $line[1];

                if ($line[0] === 'admin') {
                    // 存在客服
                    // 检查是否在线
                    if (Connection::isOnline($user_type , $user_id)) {
                        // 该用户没有登录任何平台
                        $exists_online_servcie = true;
                    }
                }
            }

            // 无论该客服是否存在
            // 这边会碰到一种情况，存在客服，但是该客服却不在该线上聊天室内！！
            // 这应该是由于 gatewayworker 在用户下线后，会自动删掉聊天室内成员导致的
            // 所以无论用户是否在线，都请加入到该聊天室内！
            if ($exists_online_servcie) {
                var_dump('存在客服，数据直接发送，不用进行客服分配');

                self::send($type , $data);
                self::persistent($data['room_id']);
                // self::pushToAdmin($client_id);
                self::pushToRelated($client_id);

                return ;
            }
            // ---- 检查是否存在客服 end

            // ----自动分配客服 start
            $allocate = self::autoAllocate($data['room_type'] , $data['room_id']);

            if ($allocate !== false) {
                var_dump('自动分配客服');

                // 发送数据
                self::send($type , $data);
                // self::pushToAdmin($client_id);
                self::persistent($data['room_id']);
                self::pushToRelated($client_id);
            } else {
                var_dump('无客服');

                // 无在线客服
                // 该聊天室临时保存起来
                self::saveTmpRoom($data['room_type'] , $data['room_id']);

                self::send($type ,$data);
                self::persistent($data['room_id']);
                // self::pushToAdmin($client_id);
                self::pushToRelated($client_id);
            }

            // ----自动分配客服 end
        }
    }

    // 订单咨询（非争议订单咨询）
    public static function order($client_id , $type , $data){
        // 为该消息生成唯一 id
        $data['identifier'] = self::genCode();

        // 获取在线聊天室成员
        $type = 'order';

        /*
        // 失败测试
        $json = Message::error($type , $data['callback'] , '数据测试');

        Gateway::sendToClient($client_id , $json);
        */

        // 加入到聊天室（这个不会重复添加）
        Room::joinRoom($data['room_id'] , $data['user_type'] , $data['user_id']);

        if ($data['user_type'] === 'admin') {
            $k_for_order_service_lock = "order_service_lock_{$data['room_id']}";

            if (redis()->exists($k_for_order_service_lock)) {
                $v_for_advoise_serivce_lock = redis()->get($k_for_order_service_lock);

                // 如果存在
                if ($v_for_advoise_serivce_lock !== "{$data['user_type']}_{$data['user_id']}") {
                    $json = Message::error($type , $data['callback'] , '此订单已经有客服在处理了');
                    Gateway::sendToClient($client_id , $json);
                    return ;
                }
            } else {
                redis()->set($k_for_order_service_lock , "{$data['user_type']}_{$data['user_id']}");
            }

            // 发送消息
            self::send($type , $data);
            self::persistent($data['room_id']);
            // self::pushToAdmin($client_id);
            self::pushToRelated($client_id);
        } else {
            self::send($type ,$data);
            self::persistent($data['room_id']);
            // self::pushToAdmin($client_id);
            self::pushToRelated($client_id);
        }
    }

    // 删除离线用户
    public static function destroyUser($client_id){
        var_dump('用户下线');

        // ----- 删除客户端id 与 用户连接的对应关系 start
        $k_for_client = "client_{$client_id}";

        if (!redis()->exists($k_for_client)) {
            var_dump('不存在对应的客户端连接数据');
            return ;
        }

        $v_for_client = redis()->get($k_for_client);
        redis()->del($k_for_client);
        // ----- 删除客户端id 与 用户连接的对应关系 end

        // ----- 删除用户连接信息 start
        $k_for_conn = "conn_{$v_for_client}";

        if (!redis()->exists($k_for_conn)) {
            var_dump('不存在用户连接数据');
            return ;
        }

        $conn = redis()->get($k_for_conn);
        $conn = json_decode($conn , true);

        redis()->del($k_for_conn);
        // ----- 删除用户连接信息 end

        // 通知相关房间用户下线 --- start
        // 检查是否所有用户都下线了
        self::notification($conn['platform'] , $conn['user_type'] , $conn['user_id'] ,'off');
        // 通知相关房间用户下线 --- end

        // 检查是否还存在用户连接
        // 如果所有平台用户连接都下线了
        // 销毁用户信息（不单单销毁当前下线的用户连接信息）
        if (!Connection::isOnline($conn['user_type'] , $conn['user_id'] , [$conn['platform']])) {
            $k_for_user_info = "user_info_{$conn['user_type']}_{$conn['user_id']}";

            redis()->del($k_for_user_info);
        }

        // 删除所有该用户发起的正在咨询的订单（如果有的话）---start
        $kv = md5("{$conn['user_type']}_{$conn['user_id']}_{$conn['client_id']}");
        $k_for_order_consultation_for_user = "order_consultation_for_user_{$kv}";
        $v_for_order_consultation_for_user = redis()->sMembers($k_for_order_consultation_for_user);

        foreach ($v_for_order_consultation_for_user as $v)
        {
            if (!redis()->exists($v)) {
                continue ;
            }

            $lock = redis()->get($v);
            $lock = json_decode($lock , true);

            // 通知聊天室所有在线成员销毁正在咨询的订单
            $json = Message::success('unlock_order' , '' , $lock);

            Gateway::sendToGroup($lock['room_id'] , $json);

            // 删除 key
            redis()->del($v);
        }
        redis()->del($k_for_order_consultation_for_user);
        // 删除所有该用户发起的正在咨询的订单（如果有的话）---end

        // ----- 删除房间在线用户 + 删除锁定的会话 start
        $rooms = Room::getRooms($conn['user_type'] , $conn['user_id']);

        foreach ($rooms as $v)
        {
            // 删除保存有该用户的房间
            $users = RoomUser::getOnlineRoomUser($v['id']);

            foreach ($users as $v1)
            {
                if ($v1 == "{$conn['user_type']}_{$conn['user_id']}") {
                    // 表明该这个房间包含当前待删除用户
                    // 无需多想,删除即可
                    $k_for_room_user = "room_user_{$v['id']}";

                    // 这个没有删除！！！！
                    redis()->lRem($k_for_room_user , $v1 , 1);
                }
            }

            // 删除锁定的会话
            if ($conn['user_type'] === 'admin') {
                $k_for_advoise_service_lock = "advoise_service_lock_{$v['id']}";
                $v_for_advoise_service_lock = redis()->get($k_for_advoise_service_lock);

                if ($v_for_advoise_service_lock === "{$conn['user_type']}_{$conn['user_id']}") {
                    // 表示该房间当前锁定的用户就是当前离线的用户
                    // 直接删除该锁定 key 即可
                    redis()->del($k_for_advoise_service_lock);
                }
            }
        }
        // ----- 删除房间在线用户 + 删除锁定的会话 end
    }

    // 更新会话顺序
    public static function updateRoomSort($client_id , $type , $data){
        // 第一种：设置给定聊天室的会话置顶（永远置顶，不会被后面更新的会话打乱！）
        // 第二种：在现有的聊天室中置顶（会被后面的更新操作，覆盖！！）

        $top_range  = app_config('business.top');
        $top        = in_array($data['top'] , $top_range) ? $data['top'] : 'n';

        // 获取用户聊天室数据
        $rooms = Room::getRooms($data['user_type'] , $data['user_id']);

        if ($top === 'y') {
            $top_sort = app_config('app.sort_for_top');

            // 设置聊天室置顶
            Room::topRoom($data['room_id'] , $data['user_type'] , $data['user_id']);

            // 更新 redis 聊天室顺序
            Room::updateRoomSortForRedis($data['room_id'] , $data['user_type'] , $data['user_id'] , $top_sort);

            // 更新 mysql 聊天室顺序
            Room::updateRoomSortForMysql($data['room_id'] , $data['user_type'] , $data['user_id'] , $top_sort);
        } else {
            $index  = false;
            $once   = true;
            $count = count($rooms);

            // 更新 sort 值
            for ($i = 0; $i < $count; ++$i)
            {
                $cur = $rooms[$i];

                if ($cur['top']) {
                    continue ;
                }

                if ($once) {
                    $top_sort = $cur['sort'];
                    $once = false;
                }

                // 如果碰到自身的时候，终止交换
                if ($cur['id'] == $data['room_id']) {
                    $index = $i;
                    break;
                }

                // 如果是左后一个就不要做交换了
                if ($i === $count - 1) {
                    break;
                }

                // 交换
                $next = $rooms[$i + 1];
                $rooms[$i]['sort'] = $next['sort'];
            }

            // 该聊天室设置为最高权重！！
            $rooms[$index]['sort'] = $top_sort;

            // 更新 redis 缓存
            foreach ($rooms as $v)
            {
                // 更新 redis sort
                Room::updateRoomSortForRedis($v['id'] , $data['user_type'] , $data['user_id'] , $v['sort']);

                // 更新 mysql sort
                Room::updateRoomSortForMysql($v['id'] , $data['user_type'] , $data['user_id'] , $v['sort']);
            }
        }

        // 反馈回发送数据成功的消息
        $json = Message::error($type , $data['callback'] , '更新聊天室排列顺序成功');
        Gateway::sendToClient($client_id , $json);
    }

    // 绑定聊天室正在咨询的订单
    public static function orderConsultation($client_id , $type , $data){
        // 聊天室正在咨询的订单
        $k_for_order_consultation = "order_consultation_{$data['room_id']}";

        redis()->set($k_for_order_consultation , json_encode([
            'room_id'   => $data['room_id'] ,
            'order_id'  => $data['order_id']
        ]));

        // 我发起的正在咨询列表（用户下线后要进行销毁）
        $kv = md5("{$data['user_type']}_{$data['user_id']}_{$data['client_id']}");
        $k_for_order_consultation_for_user = "order_consultation_for_user_{$kv}";

        redis()->sAdd($k_for_order_consultation_for_user , $k_for_order_consultation);

        // 保存到 mysql
        $count = \DB::table('related_order')->where([
            ['room_id' , '=' , $data['room_id']] ,
            ['order_id' , '=' , $data['order_id']] ,
        ])->count();

        if ($count == 0) {
            // 不存在，插入
            \DB::table('related_order')->insert([
                'room_id' => $data['room_id'] ,
                'order_id' => $data['order_id']
            ]);
        }

        // 订单信息
        $order = Order::getOrder($data['order_id']);
        $order = array_merge([
            'room_id' => $data['room_id']
        ] , $order);
        $json = Message::success($type , $data['callback'] , $order);
        Gateway::sendToGroup($data['room_id'] , $json);
    }

    public static function getLockOrder($client_id , $type , $data){
        $k_for_order_consultation = "order_consultation_{$data['room_id']}";

        if (!redis()->exists($k_for_order_consultation)) {
            $status = 'error';
            $msg = '该聊天室没有正在咨询的相关订单';
        } else {
            $lock = redis()->get($k_for_order_consultation);
            $lock = json_decode($lock , true);
            $order = Order::getOrder($lock['order_id']);
            $status = 'success';
            $msg = $order;
        }

        $json = Message::format($type , $data['callback'] , $status , $msg);
        Gateway::sendToClient($client_id , $json);
    }

    public static function addOrderDispute($client_id , $type , $data){
        if ($data['user_type'] === 'admin') {
            // 后台用户不允许发起争议
            $status = 'error';
            $msg    = '后台客服不允许发起争议';
        } else {
            // 检查是否存在尚未解决的争议
            // 如果存在尚未解决的争议
            // 不允许添加，等待争议解决后才允许增加
            $count = Order::getUnsolveDisputeOrderCount($data['room_id']);

            if ($count > 0) {
                $status = 'error';
                $msg    = '当前聊天室存在尚未解决的争议，请等待客服处理完毕后在添加';
            } else {
                // 添加争议
                $insert = Order::addDispute($data['room_id'] , $data['order_id'] , $data['user_id'] , $data['title'] , $data['description']);

                if (!$insert) {
                    // 添加争议失败 ..
                    $status = 'error';
                    $msg    = '保存争议数据到数据库失败';
                } else {
                    // 自动分配客服！！
                    $user = self::autoAllocate($data['room_type'] , $data['room_id']);
                    $status = 'success';
                    $msg    = '添加争议成功！' . ($user ? '客服已经介入' : '尚无客服');
                }
            }
        }

        $json = Message::format($type , $data['callback'] , $status , $msg);
        Gateway::sendToClient($client_id , $json);
    }

    // 测试用接口
    public static function test($client_id , $type , $data){
        $json = Message::success($type , $data['callback'] , $data['msg']);
        Gateway::sendToClient($client_id , $json);
    }

    // 创建普通聊天室
    public static function createRoom($client_id , $type , $data){

    }

    // 创建争议聊天室
    public static function createRoomForOrderDispute($client_id , $type , $data){

    }
}