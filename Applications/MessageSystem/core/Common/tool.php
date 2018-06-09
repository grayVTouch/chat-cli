<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/26
 * Time: 23:54
 */

use Core\Lib\File;

// 自定义文件加载 + 解析函数
function config($dir , $key , $args = []){
    if (empty($key)) {
        throw new \Exception('未提供待查找的 key');
    }

    $keys   = explode('.' , $key);
    $len    = count($keys);
    $index  = 0;
    $res    = null;
    // 在内存中维护的数据
    static $data = [];

    $do = function($dir , $v , &$config = []) use(&$do , &$res , $key , $keys , $len ,  &$index , $args){
        $index++;

        $file = format_path($dir . $v);

        // var_dump($file);
        // var_dump($v);

        if (File::checkDir($file)) {
            if (!isset($config[$v])) {
                $config[$v] = null;
            }

            $file .= '/';
        } else {
            $tmp_file = $file . '.php';

            if ($len - 2 < $index && File::checkFile($tmp_file) && !isset($config[$v])) {
                $config[$v] = require_once $tmp_file;
            }
        }

        if ($index === $len) {
            if (!isset($config[$v])) {
                throw new \Exception("未找到 {$key} 对应键值");
            }

            if (is_array($config[$v])) {
                return $res = $config[$v];
            }

            return $res = vsprintf($config[$v] , $args);
        } else {
            $do($file , $keys[$index] , $config[$v]);
        }
    };

    $do($dir , $keys[$index] , $data);

    return $res;
}

// 获取应用配置文件
function app_config($key , $args = []){
    $config_dir = APP_DIR . "config/";

    return config($config_dir , $key , $args);
}

// 获取指定正确的值
// 获取正确的值
function get_explain($config_key , $key){
    $range = app_config($config_key);

    foreach ($range as $k => $v)
    {
        if ($k == $key) {
            return $v;
        }
    }

    return false;

}