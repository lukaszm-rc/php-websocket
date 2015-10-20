<?php

/**
 * Description of functions
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */

define('SHELL_CORE_PATH', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..'));
include "../vendor/autoload.php";
spl_autoload_register(function ($className) {
    $fileName = implode(DIRECTORY_SEPARATOR, [
        SHELL_CORE_PATH,"src",str_replace("\\", DIRECTORY_SEPARATOR, $className) . '.php'
    ]);
	
    if (file_exists($fileName)) {
        if (is_readable($fileName)) {
            echo $fileName." ok\n";
            require_once $fileName;
        } else {

        }
    } else {
        echo $fileName." dont exists\n";
    }
});

define("SERVER_IP","127.0.0.1");
define("SERVER_PORT","8080");
define("SERVER_PATH","/");
?>
