<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace WebSocket\Server;
/**
 * Description of WebSocketMessageInterface
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */
interface WebSocketServerInterface {

	public function onOpen(ServerConnection $client);

	//put your code here
	public function onMessage(ServerConnection $client, $message);

	public function onClose(ServerConnection $client);

	public function onError(ServerConnection $client, $e);
	//put your code here
}
