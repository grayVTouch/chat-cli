<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2017/11/29
 * Time: 16:20
 */

namespace Core\System;

class DBTableRow
{
    protected $_data = [];

    function __construct(array $data = []){
        $this->_data = $data;
    }

    // 获取首条记录
    public function first(){
        $this->_data = isset($this->_data[0]) ? $this->_data[0] : [];

        return $this;
    }

    // 获取单行记录的某个字段值
    public function value($key){
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    // fetch
    public function fetch(){
        return $this->_data;
    }
}