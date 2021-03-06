<?php

namespace WebSocket\Request;

/**
 *  Request
 * @author Łukasz Mazurek <lukasz.mazurek@boo.pl>
 */
class Request {

	const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

	/**
	 * @param $payload
	 * @param string $type
	 * @param bool $masked
	 * @return bool|string
	 */
	public static function hybi10Encode($payload, $type = 'text', $masked = true) {
		$frameHead = array ();
		$frame = '';
		$payloadLength = strlen($payload);

		switch ($type) {
			case 'text':
				// first byte indicates FIN, Text-Frame (10000001):
				$frameHead[0] = 129;
				break;

			case 'close':
				// first byte indicates FIN, Close Frame(10001000):
				$frameHead[0] = 136;
				break;

			case 'ping':
				// first byte indicates FIN, Ping frame (10001001):
				$frameHead[0] = 137;
				break;

			case 'pong':
				// first byte indicates FIN, Pong frame (10001010):
				$frameHead[0] = 138;
				break;
		}

		// set mask and payload length (using 1, 3 or 9 bytes)
		if ($payloadLength > 65535) {
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked === true)?255:127;
			for ($i = 0; $i < 8; $i++) {
				$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
			}

			// most significant bit MUST be 0 (close connection if frame too big)
			if ($frameHead[2] > 127) {
				$this->close(1004);
				return false;
			}
		}
		elseif ($payloadLength > 125) {
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked === true)?254:126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		}
		else {
			$frameHead[1] = ($masked === true)?$payloadLength + 128:$payloadLength;
		}

		// convert frame-head to string:
		foreach (array_keys($frameHead) as $i) {
			$frameHead[$i] = chr($frameHead[$i]);
		}

		if ($masked === true) {
			// generate a random mask:
			$mask = array ();
			for ($i = 0; $i < 4; $i++) {
				$mask[$i] = chr(rand(0, 255));
			}

			$frameHead = array_merge($frameHead, $mask);
		}
		$frame = implode('', $frameHead);
		// append payload to frame:
		for ($i = 0; $i < $payloadLength; $i++) {
			$frame .= ($masked === true)?$payload[$i] ^ $mask[$i % 4]:$payload[$i];
		}

		return $frame;
	}

	/**
	 * @param $data
	 * @return null|string
	 */
	public static function hybi10Decode($data) {
		if (empty($data)) {
			return null;
		}

		$bytes = $data;
		$dataLength = '';
		$mask = '';
		$coded_data = '';
		$decodedData = '';
		$secondByte = sprintf('%08b', ord($bytes[1]));
		$masked = ($secondByte[0] == '1')?true:false;
		$dataLength = ($masked === true)?ord($bytes[1]) & 127:ord($bytes[1]);

		if ($masked === true) {
			if ($dataLength === 126) {
				$mask = substr($bytes, 4, 4);
				$coded_data = substr($bytes, 8);
			}
			elseif ($dataLength === 127) {
				$mask = substr($bytes, 10, 4);
				$coded_data = substr($bytes, 14);
			}
			else {
				$mask = substr($bytes, 2, 4);
				$coded_data = substr($bytes, 6);
			}
			for ($i = 0; $i < strlen($coded_data); $i++) {
				$decodedData .= $coded_data[$i] ^ $mask[$i % 4];
			}
		}
		else {
			if ($dataLength === 126) {
				$decodedData = substr($bytes, 4);
			}
			elseif ($dataLength === 127) {
				$decodedData = substr($bytes, 10);
			}
			else {
				$decodedData = substr($bytes, 2);
			}
		}
		return $decodedData;
	}

	public static function getMessage($_response, $decode = true) {
		if ($decode) {
			$_response = Request::hybi10Decode($_response);
		}
		if (isset($_response['content'])) {
			$content = trim($_response['content']);
		}
		else {
			$content = $_response;
		}
		//$_content=$response;
		if (preg_match('/(\{.*\})/', $content, $match)) {
			$content = json_decode($match[1], true);
			return $content;
		}
	}

	public static function getHeaders($string) {
		if (preg_match_all("#([^:]+):([^\n]+)\n#", $string, $match)) {
			foreach ($match[1] as $k => $m) {
				$head[trim($match[1][$k])] = trim($match[2][$k]);
			}
		}
		return $head;
	}

	public static function getSign($key) {
		return base64_encode(sha1($key . Request::GUID, true));
	}

	public static function parseIncomingRaw($header) {
		$retval = array ();
		$content = "";
		$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
		foreach ($fields as $field) {
			if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
				$match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($matches) {
					return strtoupper($matches[0]);
				}, strtolower(trim($match[1])));
				if (isset($retval[$match[1]])) {
					$retval[$match[1]] = array ($retval[$match[1]], $match[2]);
				}
				else {
					$retval[$match[1]] = trim($match[2]);
				}
			}
			else if (preg_match('!HTTP/1\.\d (\d)* .!', $field)) {
				$retval["status"] = $field;
			}
			else {
				$content .= $field . "\r\n";
			}
		}
		$retval['content'] = $content;

		return $retval;
	}

}
