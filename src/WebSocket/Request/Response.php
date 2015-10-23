<?php

namespace WebSocket\Request;

/**
 * Guzzle HTTP response object
 */
class Response implements \Serializable {

	/**
	 * @var array Array of reason phrases and their corresponding status codes
	 */
	private static $statusTexts = array (
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Reserved for WebDAV advanced collections expired proposal',
		426 => 'Upgrade required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates (Experimental)',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required',
	);

	/** @var EntityBodyInterface The response body */
	protected $body = null;

	/** @var string The reason phrase of the response (human readable code) */
	protected $reasonPhrase;

	/** @var string The status code of the response */
	protected $statusCode;

	/** @var array Information about the request */
	protected $info = array ();

	/** @var string The effective URL that returned this response */
	protected $effectiveUrl;

	protected $headers;

	/** @var array Cacheable response codes (see RFC 2616:13.4) */
	protected static $cacheResponseCodes = array (200, 203, 206, 300, 301, 410);

	/**
	 * Construct the response
	 *
	 * @param string                              $statusCode The response status code (e.g. 200, 404, etc)
	 * @param array              $headers    The response headers
	 * @param string|resource|EntityBodyInterface $body       The body of the response
	 *
	 * @throws BadResponseException if an invalid response code is given
	 */
	public function __construct($statusCode, $headers = null, $body = null) {
		$this->setStatus($statusCode);
		$this->body = null;
		if ($headers && is_array($headers)) {
			$this->headers = $headers;
		}
		else {
			throw new BadResponseException('Invalid headers argument received');
		}
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->getMessage();
	}

	public function serialize() {
		return json_encode(array (
			'status' => $this->statusCode,
			'body' => (string) $this->body,
			'headers' => $this->headers->toArray()
		));
	}

	public function unserialize($serialize) {
		$data = json_decode($serialize, true);
		$this->__construct($data['status'], $data['headers'], $data['body']);
	}

	/**
	 * Get the response entity body
	 *
	 * @param bool $asString Set to TRUE to return a string of the body rather than a full body object
	 *
	 * @return EntityBodyInterface|string
	 */
	public function getBody($asString = false) {
		return $asString?(string) $this->body:$this->body;
	}

	/**
	 * Set the response entity body
	 *
	 * @param EntityBodyInterface|string $body Body to set
	 *
	 * @return self
	 */
	public function setBody($body) {
		$this->body = EntityBody::factory($body);
		return $this;
	}

	public function setProtocol($protocol, $version) {
		$this->protocol = $protocol;
		$this->protocolVersion = $version;
		return $this;
	}

	/**
	 * Get the protocol used for the response (e.g. HTTP)
	 *
	 * @return string
	 */
	public function getProtocol() {
		return $this->protocol;
	}

	/**
	 * Get the HTTP protocol version
	 *
	 * @return string
	 */
	public function getProtocolVersion() {
		return $this->protocolVersion;
	}

	/**
	 * Set the response status
	 *
	 * @param int    $statusCode   Response status code to set
	 * @param string $reasonPhrase Response reason phrase
	 *
	 * @return self
	 * @throws BadResponseException when an invalid response code is received
	 */
	public function setStatus($statusCode, $reasonPhrase = '') {
		$this->statusCode = (int) $statusCode;

		if (!$reasonPhrase && isset(self::$statusTexts[$this->statusCode])) {
			$this->reasonPhrase = self::$statusTexts[$this->statusCode];
		}
		else {
			$this->reasonPhrase = $reasonPhrase;
		}

		return $this;
	}

	public function getStatusCode() {
		return $this->statusCode;
	}

	public function getMessage() {
		$message = $this->getRawHeaders();
		$size = strlen($this->body);
		if ($size < 2097152) {
			$message .= (string) $this->body;
		}
		return $message;
	}

	/**
	 * Get the the raw message headers as a string
	 *
	 * @return string
	 */
	public function getRawHeaders() {
		$headers = 'HTTP/1.1 ' . $this->statusCode . ' ' . $this->reasonPhrase . "\r\n";
		$lines = $this->getHeaderLines();
		if (!empty($lines)) {
			$headers .= implode("\r\n", $lines) . "\r\n";
		}

		return $headers . "\r\n";
	}

	public function getHeaderLines() {
		$lines = array ();
		foreach ($this->headers as $key => $item) {
			$lines[] = $key . ": " . $item;
		}
		return $lines;
	}

}
