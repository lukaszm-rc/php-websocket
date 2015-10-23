<?php

namespace WebSocket\Server;

/**
 * Description of WebSocketMessageInterface
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */
interface WebSocketServerInterface {

	public function onOpen(ServerConnection $client);

	public function onMessage(ServerConnection $client, $message);

	public function onClose(ServerConnection $client);

	public function onError(ServerConnection $client, $e);
}
