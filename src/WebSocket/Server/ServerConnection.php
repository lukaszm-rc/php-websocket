<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace WebSocket\Server;

use React\Socket\ConnectionInterface;

/**
 * {@inheritdoc}
 */
class ServerConnection {

	/**
	 * @var \React\Socket\ConnectionInterface
	 */
	protected $conn;

	/**
	 * @param \React\Socket\ConnectionInterface $conn
	 */
	public function __construct(ConnectionInterface $conn) {
		$this->conn = $conn;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send($data) {
		$this->conn->write($data);

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function close() {
		$this->conn->end();
	}

}
