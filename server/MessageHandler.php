<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MessageHandler
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */
use WebSocket\Server\ServerConnection;

class MessageHandler implements WebSocket\Server\WebSocketServerInterface {

	protected $clients = array ();

	public function onMessage(ServerConnection $client, $message) {
		print_r(['message' => $message]);
	}

	public function onOpen(ServerConnection $client) {
		//print_r($client);
		$this->clients[$client->resourceId] = $client;
		$this->message("Client " . $client->resourceId . " connected");
	}

	public function onClose(ServerConnection $client) {
		print_r($client);
		$this->message("Client " . $client->resourceId . " disconnected");
	}
	public function onError(ServerConnection $client, $e) {
		echo "-";
		print_r($e);
	}

	protected function message($message) {
		$status = "[ current connections " . count($this->clients) . " ]";
		echo $message . " " . $status . "\n";
	}

}
