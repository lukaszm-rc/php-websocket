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

	protected $clients;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onMessage(ServerConnection $client, $message) {
		$client->
				print_r(['message' => $message]);
	}

	public function onOpen(ServerConnection $conn) {
		$this->clients->attach($conn);
		$conn->send(json_encode(["id" => rand(1000, 2000), "type" => "request", "args" => "ping"]));
		$this->message("Client " . $conn->resourceId . " connected");
		$conn->WebSocket = new \StdClass;
//		$conn->WebSocket->request = $request;
		$conn->WebSocket->established = false;
		$conn->WebSocket->closing = false;
		$this->attemptUpgrade($conn);
	}

	public function onClose(ServerConnection $client) {
		$this->clients->detach($client);
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

	protected function attemptUpgrade(WebSocket\Server\ServerConnection $conn, $data = '') {
		if ('' !== $data) {
			$conn->WebSocket->request->getBody()->write($data);
		}
		else {
			if (!$this->versioner->isVersionEnabled($conn->WebSocket->request)) {
				return $this->close($conn);
			}
			$conn->WebSocket->version = $this->versioner->getVersion($conn->WebSocket->request);
		}
		try {
			$response = $conn->WebSocket->version->handshake($conn->WebSocket->request);
		}
		catch (\UnderflowException $e) {
			return;
		}
		if (null !== ($subHeader = $conn->WebSocket->request->getHeader('Sec-WebSocket-Protocol'))) {
			if ('' !== ($agreedSubProtocols = $this->getSubProtocolString($subHeader->normalize()))) {
				$response->setHeader('Sec-WebSocket-Protocol', $agreedSubProtocols);
			}
		}
		$response->setHeader('X-Powered-By', \Ratchet\VERSION);
		$conn->send((string) $response);
		if (101 != $response->getStatusCode()) {
			return $conn->close();
		}
		$upgraded = $conn->WebSocket->version->upgradeConnection($conn, $this->component);
		$this->connections->attach($conn, $upgraded);
		$upgraded->WebSocket->established = true;
		return $this->component->onOpen($upgraded);
	}

}
