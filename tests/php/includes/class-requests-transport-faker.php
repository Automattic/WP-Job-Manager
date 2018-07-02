<?php

class Requests_Transport_Faker implements Requests_Transport {
	public $headers_matter = false;
	private $_log          = array();
	private $_responses    = array();

	/**
	 * Adds a fake request and response.
	 *
	 * @param array|string $request Array (url, headers, data) or string of the signature (See: Requests_Transport_Faker::make_request_signature()).
	 * @param array $response
	 */
	public function add_fake_request( $request, $response ) {
		$response_arr = array();
		if ( is_array( $request ) ) {
			$response_arr['request'] = $request;
			$request                 = self::make_request_signature(
				isset( $request['url'] ) ? $request['url'] : '',
				isset( $request['headers'] ) ? $request['headers'] : array(),
				isset( $request['data'] ) ? $request['data'] : array()
			);
		}
		if ( is_array( $response ) ) {
			$response = self::build_response( $response );
		}
		$response_arr['response']     = $response;
		$this->_responses[ $request ] = $response_arr;
	}

	/**
	 * Creates a hash of a particular request.
	 *
	 * @param string $url
	 * @param array $headers
	 * @param array $data
	 *
	 * @return string
	 */
	public static function make_request_signature( $url, $headers = array(), $data = array() ) {
		if ( empty( $headers ) ) {
			$headers = array();
		}
		if ( empty( $data ) ) {
			$data = array();
		}
		if ( is_object( $url ) && $url instanceof Requests_IRI ) {
			$url = $url->__toString();
		}
		return sha1( json_encode( array( $url, $headers, $data ) ) );
	}

	/**
	 * Builds a response from an array.
	 *
	 * @param array $response
	 * @return string
	 */
	public static function build_response( $response ) {
		if ( ! isset( $response['headers'] ) ) {
			$response['headers'] = array( 'HTTP/1.1 200 OK' );
		}
		if ( ! isset( $response['body'] ) ) {
			$response['body'] = '';
		}
		if ( is_array( $response['body'] ) ) {
			$response['body'] = json_encode( $response['body'] );
		}
		if ( ! isset( $response['headers']['X-Realness'] ) ) {
			$response['headers']['X-Realness'] = 'Faux';
		}
		if ( ! isset( $response['headers']['Content-Length'] ) ) {
			$response['headers']['Content-Length'] = strlen( $response['body'] );
		}
		$response_str = '';
		foreach ( $response['headers'] as $header => $value ) {
			if ( is_numeric( $header ) ) {
				$response_str .= "$value\r\n";
			} else {
				$response_str .= "$header: $value\r\n";
			}
		}
		$response_str .= "\r\n";
		$response_str .= $response['body'];
		return $response_str;
	}

	public function has_request_been_made( $request_signature ) {
		return isset( $this->_log[ $request_signature ] );
	}

	public function get_request_log() {
		return $this->_log;
	}

	/**
	 * {@inheritdoc}
	 */
	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		if ( $this->headers_matter ) {
			$signature = self::make_request_signature( $url, $headers, $data );
		} else {
			$signature = self::make_request_signature( $url, array(), $data );
		}
		$this->_log[ $signature ] = array(
			'url'     => $url,
			'headers' => $headers,
			'data'    => $data,
		);
		if ( ! isset( $this->_responses[ $signature ] ) ) {
			throw new Requests_Exception( 'Computer says no', 'test' );
		}
		return $this->_responses[ $signature ]['response'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function request_multiple( $requests, $options ) {
		$responses = array();
		$class     = get_class( $this );
		foreach ( $requests as $id => $request ) {
			try {
				$handler          = new $class();
				$responses[ $id ] = $handler->request( $request['url'], $request['headers'], $request['data'], $request['options'] );

				$request['options']['hooks']->dispatch( 'transport.internal.parse_response', array( &$responses[ $id ], $request ) );
			} catch ( Requests_Exception $e ) {
				$responses[ $id ] = $e;
			}

			if ( ! is_string( $responses[ $id ] ) ) {
				$request['options']['hooks']->dispatch( 'multiple.request.complete', array( &$responses[ $id ], $id ) );
			}
		}

		return $responses;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function test() {
		return true;
	}
}
