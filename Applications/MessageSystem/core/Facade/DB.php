<?php

use Core\System\DBConnection;

class DB
{
    // 返回 DatabaseTable 的实例
    public static function table($table){
        return app()->singleton('database')->table($table);
    }

    // 返回 自身实例
    final public static function connection($key){
        return new DBConnection(app_config('database.mysql')[$key]);
    }

    public static function statement($sql , array $params = []){
        return app()->singleton('database')->statement($sql , $params);
    }

    public static function update($sql , array $params = []){
        return app()->singleton('database')->update($sql , $params);
    }

    public static function insert($sql , array $params = []){
        return app()->singleton('database')->insert($sql , $params);
    }

    public static function insertGetId($sql , array $params = []){
        return app()->singleton('database')->insertGetId($sql , $params);
    }

    public static function delete($sql , array $params = []){
        return app()->singleton('database')->delete($sql , $params);
    }

    public static function select($sql , array $params = []){
        return app()->singleton('database')->select($sql , $params);
    }

    public static function transaction(callable $function){
        return app()->singleton('database')->transaction($function);
    }

    // 格式化结果
    public static function format(\PDOStatement $stmt){
        return app()->singleton('database')->formatQRel($stmt);
    }
}
