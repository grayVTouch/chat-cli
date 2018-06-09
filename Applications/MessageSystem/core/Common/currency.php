<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/27
 * Time: 0:24
 */

function app(){
    return $GLOBALS['app'];
}

function db(){
    return app()->singleton('database');
}

function redis(){
    return app()->singleton('redis');
}