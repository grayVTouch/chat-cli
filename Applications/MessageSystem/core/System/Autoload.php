<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/26
 * Time: 23:34
 */

namespace Core\System;


class Autoload
{
    function __construct(){

    }

    // 自动加载
    public function register($register){
        // 加载类
        $this->loadClass($register['class']);
        // 加载文件
        $this->loadFile($register['file']);
    }

    // 注册自动加载函数
    public function loadClass($class){
        foreach ($class as $k => $v)
        {
            call_user_func(function($k , $v){
                // 注册自动加载函数
                spl_autoload_register(function($classname) use($k , $v){
                    $class = $v . str_replace($k , '' , $classname);
                    $class = str_replace('\\' , '/' , $class);
                    $class .= '.php';

                    if (!file_exists($class)) {
                        return ;
                    }

                    require_once $class;
                });
            } , $k , $v);
        }
    }

    // 加载文件
    public function loadFile($file){
        foreach ($file as $v)
        {
            if (!file_exists($v)) {
                continue ;
            }

            require_once $v;
        }
    }
}