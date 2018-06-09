<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use GatewayWorker\Lib\Gateway;
use App\System\Business;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id) {
        
    }
    
    /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
    public static function onMessage($client_id, $msg) {
        $data       = json_decode($msg , true);
        $type       = $data['msg_type'];
        $content    = $data['content'];

        switch ($type)
        {
            case 'test':
                Business::test($client_id , $type , $content);
                break;
            case 'login':
                Business::login($client_id , $type , $content);
                break;
            case 'advoise':
                // 平台咨询
                Business::advoise($client_id , $type , $content);
                break;
            case 'order':
                // 订单咨询
                Business::order($client_id , $type , $content);
                break;
            case 'broadcast':
                // 系统消息广播
                Business::broadcast($client_id , $type , $content);
                break;
            case 'user':
                // 手动同步聊天室成员
                Business::user($client_id , $type , $content);
                break;
            case 'syn':
                Business::syn($client_id , $type , $content);
                break;
            case 'auto_allocate':
                Business::autoAllocateAsAPI($client_id , $type , $content);
                break;
            case 'join_room':
                Business::joinRoom($client_id , $type , $content);
                break;
            case 'empty_msg_count':
                Business::emptyMsgCount($client_id , $type , $content);
                break;
            case 'get_room':
                Business::getRoom($client_id , $type , $content);
                break;
            case 'update_room_sort':
                // 更新会话的顺序
                Business::updateRoomSort($client_id , $type , $content);
                break;
            case 'order_consultation':
                // 咨询订单信息
                // 用于绑定当前聊天室正在咨询的订单
                Business::orderConsultation($client_id , $type , $content);
                break;
            case 'get_lock_order':
                // 手动获取聊天室主题事务
                Business::getLockOrder($client_id , $type , $content);
                break;
            case 'add_order_dispute':
                // 申请客服介入解决争议
                Business::addOrderDispute($client_id , $type , $content);
                break;
            default:
                throw new Exception("不支持的消息类型：{$type}");
        }
    }
   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id) {
        Business::destroyUser($client_id);
   }

}
