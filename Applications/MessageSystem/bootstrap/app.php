<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/26
 * Time: 23:41
 */

// 定义系统常量
const ROOT_DIR  = __DIR__ . '/../../../';
const APPS_DIR  = ROOT_DIR . 'Applications/';
const APP_DIR   = APPS_DIR . 'MessageSystem/';
const _APP_DIR   = APP_DIR . 'app/';
const CORE_DIR  = APP_DIR . 'core/';
const BOOTSTRAP_DIR = APP_DIR . 'bootstrap/';
const CONFIG_DIR    = APP_DIR . 'config/';
const STORAGE_DIR    = APP_DIR . 'storage/';

// 引入自动加载文件
require_once __DIR__ . '/autoload.php';

use Core\System\Application;

$app = new Application();