<?php
/**
 * Created by PhpStorm.
 * User: grayVTouch
 * Date: 2018/4/26
 * Time: 23:41
 */

require_once CORE_DIR . 'System/Autoload.php';

use Core\System\Autoload;

$autoload = new Autoload();

$register = [
    'class' => [
        "\\" => CORE_DIR . "/Facade/" ,
        // 个人类库
        "Core\\" => CORE_DIR ,
        'App\\' => _APP_DIR ,
    ] ,
    'file' => [
        // 个人函数库
        CORE_DIR . "Function/array.php" ,
        CORE_DIR . "Function/base.php" ,
        CORE_DIR . "Function/file.php" ,
        CORE_DIR . "Function/string.php" ,
        CORE_DIR . "Function/time.php" ,
        CORE_DIR . "Function/url.php" ,

        // 应用函数库
        CORE_DIR . 'Common/tool.php' ,
        CORE_DIR . 'Common/currency.php' ,
    ]
];

// 自动加载
$autoload->register($register);