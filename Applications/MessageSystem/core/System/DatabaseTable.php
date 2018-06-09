<?php

/**
 * **************************************
 * author grayVTouch 2017-11-28
 * 数据库表
 * **************************************
 */

namespace Core\System;

use Core\Lib\Database;

class DatabaseTable
{
    // select
    protected $_select = [];

    // join
    protected $_join = [];

    // where
    protected $_where = null;

    // orderBy
    protected $_orderBy = [];

    // groupBy
    protected $_groupBy = null;

    // having
    protected $_having = null;

    // offset
    protected $_offset = null;

    // limit
    protected $_limit = null;

    // in
    protected $_in = [];

    // not in
    protected $_notIn = [];

    // table
    protected $_table = '';

    // Database 实例
    protected $_db = null;

    // 当前执行的 sql 语句
    public static $sql = '';

    function __construct(Database $db , $table , $prefix){
        $this->_db      = $db;
        $this->_table   = $table;
        $this->_prefix  = $prefix;

        // 数据库查询日志
        $db_log = app()->singleton('db_log');

        if (empty($db_log)) {
            // 注册
            app()->singleton('db_log' , new Logs(app_config('log.log_dir') , 'db' , false));

            // 日志
            $db_log = app()->singleton('db_log');
        }

        $this->_log = $db_log;
    }

    // select
    public function select(...$selects){
        $this->_select = $selects;

        return $this;
    }

    // join
    public function join($type , $table , $compare_left , $compare_condition , $compare_right){
        $this->_join[] = [
            'type'              => $type ,
            'table'             => $table ,
            'compare_left'      => $compare_left ,
            'compare_condition' => $compare_condition ,
            'compare_right'     => $compare_right
        ];
    }

    // leftJoin
    public function leftJoin($table , $compare_left , $compare_condition , $compare_right){
        $this->join('left join' , $table , $compare_left , $compare_condition , $compare_right);

       return $this;
    }

    // leftJoin
    public function rightJoin($table , $compare_left , $compare_condition , $compare_right){
        $this->join('right join' , $table , $compare_left , $compare_condition , $compare_right);

        return $this;
    }

    // innerJoin
    public function innerJoin($table , $compare_left , $compare_condition , $compare_right){
        $this->join('inner join' , $table , $compare_left , $compare_condition , $compare_right);

        return $this;
    }

    // orderBy
    public function orderBy($field , $order = 'asc'){
        $this->_orderBy[] = [
            'field' => $field ,
            'order' => $order
        ];

        return $this;
    }

    // groupBy
    public function groupBy($group){
        $this->_groupBy = $group;

        return $this;
    }

    // having
    public function having($condition){
        $this->_having = $condition;

        return $this;
    }

    // offset
    public function offset($offset){
        $this->_offset = $offset;

        return $this;
    }

    // limit
    public function limit($limit){
        $this->_limit = $limit;

        return $this;
    }

    // where
    public function where(...$args){
        if (count($args) === 2) {
            $this->_where = [
                [$args[0] , '=' , $args[1]]
            ];
        } else {
            $this->_where = $args[0];
        }

        return $this;
    }

    // 字符串拼接：where
    protected function _where_(&$sql , array &$params = []){
        if (!empty($this->_where)) {
            $sql .= ' where ';

            // 针对某一个键名的统计情况（出现次数，由于出现次数如果超过了一次，那么在 where 判断的时候可能会出现覆盖的情况）
            $statistics = [];

            array_walk($this->_where , function($v) use(&$keys , &$statistics){
                $statistics[$v[0]]  = 0;
            });

            foreach ($this->_where as $v)
            {
                $statistics[$v[0]]++;

                $key =  md5('_' . $statistics[$v[0]] . '_' . $v[0]);
                $condition = (!isset($v[3]) ? 'and' : $v[3]) . ' ';
                $sql .= $v[0] . ' ' . $v[1] . ' :' . $key . ' ' . $condition;
                $params[$key] = $v[2];
            }

            $sql = mb_substr($sql , 0 , mb_strlen($sql) - mb_strlen($condition));
        }
    }

    // 字符串拼接：group by
    protected function _groupBy_(&$sql){
        if (!empty($this->_groupBy)) {
            $sql .= ' group by ' . $this->_groupBy;
        }
    }

    // 字符串拼接：having
    protected function _having_(&$sql){
        if (!empty($this->_having)) {
            $sql .= ' having ' . $this->_having;
        }
    }

    // 字符串拼接：order by
    protected  function _orderBy_(&$sql){
        // orderBy
        if (!empty($this->_orderBy)) {
            $sql .= ' order by';

            foreach ($this->_orderBy as $v)
            {
                $sql .= ' ' . $v['field'] . ' ' . $v['order'] . ' ,';
            }

            $sql = rtrim($sql , ',');
        }
    }

    // 字符串拼接：offset
    protected  function _offset_(&$sql){
        if (is_numeric($this->_offset)) {
            $sql .= ' limit ' . $this->_offset . ' , ';
        }
    }

    // 字符串拼接：limit
    protected  function _limit_(&$sql){
        if (is_numeric($this->_limit)) {
            if (is_numeric($this->_offset)) {
                $sql .= $this->_limit;
            } else {
                $sql .= ' limit ' . $this->_limit;
            }
        }
    }

    // 字符串拼接：join
    protected function _join_(&$sql){
        foreach ($this->_join as $v)
        {
            $sql .= ' ' . $v['type'] . ' ' . $this->_prefix .  $v['table'] . ' on ' . $v['compare_left'] . ' ' . $v['compare_condition'] . ' ' . $v['compare_right'];
        }
    }

    // 字符串拼接：in
    protected function _in_(&$sql){
        if (empty($this->_in)) {
            return ;
        }

        if (empty($this->_where)) {
            $sql .= " where ";
        } else {
            // 拼接最后一个 where 条件中带的拼接条件
            $last = $this->_where[count($this->_where) - 1];
            $sql .= !isset($last[3]) ? 'and ' : $last[3] . ' ';
        }

        $condition = '';

        foreach ($this->_in as $v)
        {
            $condition = $v[3] . ' ';

            $sql .= $v[0] . ' ' . $v[1] . ' (' . $v[2] . ') ' .  $condition;
        }

        $sql = mb_substr($sql , 0 , mb_strlen($sql) - mb_strlen($condition));
    }

    // 字符串拼接：not in
    protected function _notIn_(&$sql){
        if (empty($this->_notIn)) {
            return ;
        }

        if (empty($this->_where && empty($this->_in))) {
            $sql .= " where ";
        }

        foreach ($this->_notIn as $v)
        {
            $sql .= $v[0] . ' ' . $v[1] . ' (' . $v[2] . ')';
        }
    }

    // 记录查询日志
    protected function _record($sql){
        $log = sprintf("[%s] %s\r\n" , date('Y-m-d H:i:s') , $sql);

        $this->_log->log($log);
    }

    // update：返回受影响的行数
    public function update(array $data = [] , $debug = false){
        if (empty($data)) {
            return ;
        }

        $sql    = 'update ';
        $sql   .= $this->_prefix . $this->_table;
        $params = [];

        $this->_join_($sql);

        $sql .= ' set';

        foreach ($data as $k => $v)
        {
            $key = md5('__' . $k);
            $sql .= ' ' . $k . ' = :' . $key . ' ,';
            $params[$key] = $v;
        }

        $sql = rtrim($sql , ',');

        // where 字符串拼接
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        // 记录查询日志
        $this->_record($sql);

        $this->_db->query($sql , $params);

        // 返回受影响的行数
        return $this->_db->rowCount();
    }

    // select，返回 DBTableRow 对象
    public function get($debug = false){
        $sql    = 'select ';
        $params = [];

        if (empty($this->_select)) {
            $sql .= '*';
        } else {
            $sql .= implode(' , ' , $this->_select);
        }

        $sql .= ' from ' . $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        // 记录数据库查询日志
        $this->_record($sql);

        return new DBTableRow($this->_db->getAll($sql , $params));
    }

    // 查询单条记录的函数
    public function find($debug = false){
        if ($debug) {
            return $this->get(true);
        }

        return $this->get()->first()->fetch();
    }

    // 计算
    protected function cal($type , $name , $amount , $debug){
        $type_range = ['incr' , 'decr'];
        $type       = in_array($type , $type_range) ? $type : 'incr';
        
        $this->select($name);

        $origin = floatval($this->get()->first()->value($name));
        $change = $type === 'incr' ? $origin + $amount : $origin - $amount;

        return $this->update([
            $name => $change
        ] , $debug);
    }

    // 针对单个字段 +1
    public function incr($name , $amount = 1 , $debug = false){
        return $this->cal('incr' , $name , $amount , $debug);
    }

    // 针对单个字段 -1
    public function decr($name , $amount , $debug = false){
        return $this->cal('decr' , $name , $amount , $debug);
    }

    // 合计函数
    public function count($debug = false){
        $sql    = 'select count(*) from ';
        $params = [];

        $sql .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $res = $this->_db->get($sql , $params);

        return  is_numeric($res) ? (int) $res : $res;
    }

    // 合计函数
    public function sum($column , $debug = false){
        $sql    = "select sum({$column}) from ";
        $params = [];

        $sql .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $res = $this->_db->get($sql , $params);

        return  is_numeric($res) ? (float) $res : $res;
    }

    // 平均值函数
    public function avg($column , $debug = false){
        $sql    = "select avg({$column}) from ";
        $params = [];

        $sql .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $res = $this->_db->get($sql , $params);

        return  is_numeric($res) ? (float) $res : $res;
    }

    // 最大值
    public function max($column , $debug = false){
        $sql    = "select max({$column}) from ";
        $params = [];

        $sql .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        return $this->_db->get($sql , $params);
    }

    // 最大值
    public function min($column){
        $sql    = "select min({$column}) from ";
        $params = [];

        $sql .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        // 保存当前执行的 sql 语句
        self::$sql = $sql;

        $this->_record($sql);

        return $this->_db->get($sql , $params);
    }

    // in 操作
    public function in($column , array $range = [] , $join = 'and'){
        if (!empty($range)) {
            $this->_in[] = [$column , 'in' , implode(' , ' , $range) , $join];
        }

        return $this;
    }

    // not in 操作
    public function notIn($column , array $range = []){
        if (!empty($range)) {
            $this->_notIn[] = [$column , 'not in' , implode(' , ' , $range)];
        }

        return $this;
    }

    // 删除
    public function delete($debug = false){
        $params = [];
        $sql    = 'delete from ';
        $sql   .= $this->_prefix . $this->_table;

        $this->_join_($sql);
        $this->_where_($sql , $params);
        $this->_in_($sql);
        $this->_notIn_($sql);
        $this->_groupBy_($sql);
        $this->_having_($sql);
        $this->_orderBy_($sql);
        $this->_offset_($sql);
        $this->_limit_($sql);

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $this->_db->query($sql , $params);

        return $this->_db->rowCount();
    }

    // insert，返回受影响的行数
    public function insert(array $data = [] , $debug = false){
        $params = [];
        $sql = 'insert into  ';
        $sql .= $this->_prefix . $this->_table;

        $keys = array_keys($data);

        $sql .= '(' . implode(' , ' , $keys) . ')';
        $sql .= ' values ';
        $sql .= '(';

        foreach ($data as $k => $v)
        {
            $key = md5($k);
            $sql .= ':' . $key . ' ,';
            $params[$key] = $v;
        }
        $sql = rtrim($sql , ',');

        $sql .= ')';

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $this->_db->query($sql , $params);

        return $this->_db->rowCount();
    }

    // insertGetId，返回插入记录的id
    public function insertGetId(array $data = [] , $debug = false){
        $params = [];
        $sql = 'insert into  ';
        $sql .= $this->_prefix . $this->_table;

        $keys = array_keys($data);

        $sql .= '(' . implode(' , ' , $keys) . ')';
        $sql .= ' values ';
        $sql .= '(';

        foreach ($data as $k => $v)
        {
            $key = md5($k);
            $sql .= ':' . $key . ' ,';
            $params[$key] = $v;
        }

        $sql = rtrim($sql , ',');

        $sql .= ')';

        if ($debug) {
            return $sql;
        }

        $this->_record($sql);

        $this->_db->query($sql , $params);

        return $this->_db->lastInsertId();
    }

    // 记录日志
    public function log($sql = ''){
        // 记录数据库查询日志
        $log = sprintf("[%s] %s\r\n" , date('Y-m-d H:i:s') , $sql);
        $this->_log->log($log);
    }
}

