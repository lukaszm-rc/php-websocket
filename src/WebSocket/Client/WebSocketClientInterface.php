<?php

namespace WebSocket\Client;

/**
 * Description of functions
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */
interface WebSocketClientInterface  {

	public function onConnect();

	public function onMessage($data);
	
	public function setClient(WebSocketConnection $client);
	
}
