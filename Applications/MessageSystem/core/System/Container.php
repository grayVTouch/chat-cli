<?php
/**
 * ***************************
 * author grayVTouch 2017-11-26
 * IOC 服务容器
 * ***************************
 */

namespace Core\System;


class Container
{
    // 注册表 key => callback
    protected $_binds = [];

    // 单例：不允许重复实例化
    protected $_share = [];

    // 实例：可被覆盖
    protected $_instance = [];

    // 获取已经存在的键值
    public function keys(){
        return array_merge($this->_binds , $this->_share , $this->_instance);
    }

    /**
     * @param $key
     * @param $callback
     * @param $is_share 是否单例
     */
    public function bind($key , callable $callback){
        if (in_array($key , $this->_share)) {
            throw new \Exception("{$key} 单例已存在");
        }

        if (in_array($key , $this->_instance)) {
            throw new \Exception("{$key} 实例已存在");
        }

        $this->_binds[$key] = $callback;
    }

    /**
     * @param $key
     * @param $args callback || instance
     * @return NULL|instance
     */
    public function singleton($key , $args = null){
        if (!isset($args)) {
            if (!in_array($key , array_keys($this->_share))) {
                return null;
            }

            if (is_callable($this->_share[$key])) {
                $this->_share[$key] = call_user_func($this->_share[$key]);
            }

            return $this->_share[$key];
        }

        if (in_array($key , array_keys($this->_share))) {
            throw new \Exception("已存在 {$key} 单例");
        }

        $this->_share[$key] = $args;
    }

    public function instance($key , $args = null){
        if (!isset($args)) {
            if (!in_array($key , array_keys($this->_instance))) {
                return null;
            }

            if (is_callable($this->_instance[$key])) {
                $this->_instance[$key] = call_user_func($this->_instance[$key]);
            }

            return $this->_instance[$key];
        }

        $this->_instance[$key] = $args;
    }

    // 从注册表中解析出实例
    public function make($key){
        if (in_array($key , array_keys($this->_share))) {
            return $this->singleton[$key];
        }

        if (in_array($key , array_keys($this->_instance))) {
            return $this->instance($key);
        }

        if (in_array($key , array_keys($this->_binds))) {
            $this->instance($key , call_user_func($this->_binds[$key]));

            return $this->instance($key);
        }

        return null;
    }
}