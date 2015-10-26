<?php

namespace WebSocket\Client;

use React\EventLoop\LoopInterface;
use React\Socket\Connection;
use WebSocket\Client\WebSocketClientInterface;
use WebSocket\Exception\ConnectionException;
use \WebSocket\Request\Request;

/**
 * Oparty na eventLoop z ReactPHP klient WebSocket.
 *
 * @author Lukasz Mazurek <lukasz.mazurek@redcart.pl>
 */
class WebSocketConnection {

    const VERSION = '0.1.0';
    const TOKEN_LENGHT = 16;
    const MSG_CONNECTED = 1;
    const MSG_DISCONNECTED = 2;
    const MSG_LOST_CONNECTION = 3;
    const TOKEN_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';

    /** @var LoopInterface $loop */
    private $loop;

    /** @var WebSocketClientInterface $client */
    private $client;

    /** @var Connection $socket */
    private $socket;
    private $key;
    private $host;
    private $port;
    private $origin;
    private $path;
    private $connected = false;
    private $callbacks = array ();
    private $tokenCharacters;
    private $tokenCharactersCount;

    /**
     * @param WebSocketClientInterface $client
     * @param LoopInterface $loop
     * @param string $host
     * @param int $port
     * @param string $path
     * @param null|string $origin
     */
    public function __construct(WebSocketClientInterface &$client, LoopInterface &$loop, $host = '127.0.0.1', $port = 8080, $path = '/', $origin = null) {
	$this -> setLoop($loop);
	$this -> setHost($host);
	$this -> setPort($port);
	$this -> setPath($path);
	$this -> setClient($client);
	$this -> setOrigin($origin);
	$this -> tokenize();
	$this -> setKey($this -> generateToken(self::TOKEN_LENGHT));
	$this -> connect();
    }

    private function tokenize() {
	$this -> tokenCharacters = str_split(self::TOKEN_CHARACTERS);
	$this -> tokenCharactersCount = strlen(self::TOKEN_CHARACTERS) - 1;
    }

    function __destruct() {
//		$this->disconnect();
    }

    /**
     * Connect client to server
     *
     * @throws ConnectionException
     * @return $this
     */
    public function connect() {
	$root = $this;
	$client = @stream_socket_client("tcp://{$this -> getHost()}:{$this -> getPort()}");
	if (!$client) {
	    throw new ConnectionException;
	}
	$this -> setSocket(new Connection($client, $this -> getLoop()));
	$this -> getSocket() -> on('data', function ($data) use ($root) {
	    $data2 = Request::parseIncomingRaw($data);
	    $root -> parseData($data2);
	});

	$this -> getSocket() -> write($this -> createHeader());

	return $this;
    }

    public function disconnect() {
	$this -> connected = false;
	if ($this -> socket instanceof Connection) {
	    $this -> socket -> close();
	    $this -> loop -> stop();
	    return self::MSG_DISCONNECTED;
	}
    }

    /**
     * @return bool
     */
    public function isConnected() {
	return $this -> connected;
    }

    public function send($data) {
	return $this -> sendData($data);
    }

    /**
     * @param $procUri
     * @param array $args
     * @param callable $callback
     */
    public function call($procUri, array $args, callable $callback = null) {
	$callId = self::generateAlphaNumToken(16);
	$this -> callbacks[ $callId ] = $callback;

	$data = array (
	    self::TYPE_ID_CALL,
	    $callId,
	    $procUri
	);
	$data = array_merge($data, $args);
	$this -> sendData($data);
    }

    /**
     * @param $data
     * @param $header
     */
    private function receiveData($data, $header) {
	if (!$this -> isConnected()) {
	    $this -> disconnect();
	    return;
	}
	$this -> getClient() -> onMessage($data);
    }

    /**
     * @param $data
     * @param string $type
     * @param bool $masked
     */
    private function sendData($data, $type = 'text', $masked = true) {
	if (!$this -> isConnected()) {
	    $this -> disconnect();
	    return;
	}

	if (is_array($data)) {
	    $data = json_encode($data);
	}
	if ($this -> getSocket() -> write(Request::hybi10Encode($data, $type, $masked))) {
	    return true;
	}
	return false;
    }

    /**
     * Parse received data
     *
     * @param $response
     */
    private function parseData($response) {

	if (!$this -> connected && isset($response[ 'Sec-Websocket-Accept' ])) {
	    if (base64_encode(pack('H*', sha1($this -> key . Request::GUID))) === $response[ 'Sec-Websocket-Accept' ]) {
		$this -> getClient() -> onConnect();
		$this -> connected = true;
	    }
	}

	if ($this -> connected && !empty($response[ 'content' ])) {
	    $content = Request::getMessage($response, false);
	    if (is_array($content)) {
		$this -> receiveData($content, $response);
	    }
	}
    }

    /**
     * Create header for websocket client
     *
     * @return string
     */
    private function createHeader() {
	$host = $this -> getHost();
	if ($host === '127.0.0.1' || $host === '0.0.0.0') {
	    $host = 'localhost';
	}

	$origin = $this -> getOrigin() ? $this -> getOrigin() : "null";

	return
		"GET {$this -> getPath()} HTTP/1.1" . "\r\n" .
		"Origin: {$origin}" . "\r\n" .
		"Host: {$host}:{$this -> getPort()}" . "\r\n" .
		"Sec-WebSocket-Key: {$this -> getKey()}" . "\r\n" .
		"User-Agent: PHPWebSocketClient/" . self::VERSION . "\r\n" .
		"Upgrade: websocket" . "\r\n" .
		"Connection: Upgrade" . "\r\n" .
		"Sec-WebSocket-Protocol: wamp" . "\r\n" .
		"Sec-WebSocket-Version: 13" . "\r\n" . "\r\n";
    }

    /**
     * Generate token
     *
     * @param int $length
     * @return string
     */
    private function generateToken($length) {
	$useChars = array ();
	// select some random chars:
	for ($i = 0; $i < $length; $i++) {
	    $useChars[] = $this -> tokenCharacters[ mt_rand(0, $this -> tokenCharactersCount) ];
	}
	// Add numbers
	array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
	shuffle($useChars);
	$randomString = trim(implode('', $useChars));
	$randomString = substr($randomString, 0, self::TOKEN_LENGHT);
	return base64_encode($randomString);
    }

    /**
     * Generate token
     *
     * @param int $length
     * @return string
     */
    public function generateAlphaNumToken($length) {
	$characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

	srand((float) microtime() * 1000000);

	$token = '';

	do {
	    shuffle($characters);
	    $token .= $characters[ mt_rand(0, (count($characters) - 1)) ];
	} while (strlen($token) < $length);

	return $token;
    }

    /**
     * @param int $port
     * @return $this
     */
    public function setPort($port) {
	$this -> port = (int) $port;
	return $this;
    }

    /**
     * @return int
     */
    public function getPort() {
	return $this -> port;
    }

    /**
     * @param Connection $socket
     * @return $this
     */
    public function setSocket(Connection $socket) {
	$this -> socket = $socket;
	return $this;
    }

    /**
     * @return Connection
     */
    public function getSocket() {
	return $this -> socket;
    }

    /**
     * @param string $host
     * @return $this
     */
    public function setHost($host) {
	$this -> host = (string) $host;
	return $this;
    }

    /**
     * @return string
     */
    public function getHost() {
	return $this -> host;
    }

    /**
     * @param null|string $origin
     */
    public function setOrigin($origin) {
	if (null !== $origin) {
	    $this -> origin = (string) $origin;
	} else {
	    $this -> origin = null;
	}
    }

    /**
     * @return null|string
     */
    public function getOrigin() {
	return $this -> origin;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key) {
	$this -> key = (string) $key;
	return $this;
    }

    /**
     * @return string
     */
    public function getKey() {
	return $this -> key;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPath($path) {
	$this -> path = $path;
	return $this;
    }

    /**
     * @return string
     */
    public function getPath() {
	return $this -> path;
    }

    /**
     * @param WebSocketClientInterface $client
     * @return $this
     */
    public function setClient(WebSocketClientInterface $client) {
	$this -> client = $client;
	return $this;
    }

    /**
     * @return WebSocketClientInterface
     */
    public function getClient() {
	return $this -> client;
    }

    /**
     * @param LoopInterface $loop
     * @return $this
     */
    public function setLoop(LoopInterface $loop) {
	$this -> loop = $loop;
	return $this;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop() {
	return $this -> loop;
    }

}
