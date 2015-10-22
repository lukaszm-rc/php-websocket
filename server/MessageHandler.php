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
use \WebSocket\Server\ServerConnection;

use \WebSocket\Request\Request;
use \WebSocket\Request\Response;
class MessageHandler implements WebSocket\Server\WebSocketServerInterface {

    private $clients;

    public function __construct() {
	$this -> clients = new \SplObjectStorage;
    }

    public function onMessage(ServerConnection $client, $message) {
	if ($this -> establishConnection($client, $message)) {
	    //$message = Request::getMessage(Request::parseIncomingRaw($message));
	    $_message=Request::getMessage($message); 
	    print_r([ 'message' => $_message ]);
	}
    }

    public function onOpen(ServerConnection $conn) {
	$this -> message("Client " . $conn -> resourceId . " connected");
	$conn -> WebSocket = new \StdClass;
	$conn -> WebSocket -> established = false;
	$conn -> WebSocket -> closing = false;
	$this -> clients -> attach($conn);
    }

    public function establishConnection($client, $message) {
	$connected = false;
	if (count($this -> clients) > 0) {
	    foreach ($this -> clients as $_client) {
		if ($_client -> WebSocket -> established) {
		    $connected = true;
		}
	    }
	}
	if (!$connected) {
	    $client -> send($this -> handshake($message));
	    $client -> WebSocket -> established = true;
	    $this -> clients -> attach($client);
	    return false;
	}
	return true;
    }

    public function handshake($message) {
	return new Response(101, array (
	    'Upgrade' => 'websocket'
	    , 'Connection' => 'Upgrade'
	    , 'Sec-WebSocket-Accept' => Request::getSign((string) Request::getHeaders($message)[ 'Sec-WebSocket-Key' ])
	));
    }

    public function onClose(ServerConnection $client) {
	$this -> clients -> detach($client);
	$this -> message("Client " . $client -> resourceId . " disconnected");
    }

    public function onError(ServerConnection $client, $e) {
	echo "-";
	print_r($e);
    }

    protected function message($message) {
	$status = "[ current connections " . count($this -> clients) . " ]";
	echo $message . " " . $status . "\n";
    }

}
