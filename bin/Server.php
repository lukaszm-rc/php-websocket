#!/usr/bin/php
<?php
include "../vendor/autoload.php";
include "../server/MessageHandler.php";

/**
 * EventLoop
 */
$messageHandler = new MessageHandler();
$server = WebSocket\Server\WebSocketServer::Factory($messageHandler, "127.0.0.1", "8080");

/**
 * Podobne do setInterval() z Javascriptu
 */
$server->loop->addPeriodicTimer(5, function () {
	echo "+";
});
$server->run();
