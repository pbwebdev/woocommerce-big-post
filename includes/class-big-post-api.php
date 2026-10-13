<?php
/**
 * Big Post API V2 client.
 *
 * @see https://api.bigpost.com.au/swagger/index.html
 */

defined( 'ABSPATH' ) || exit;

/**
 *
 * @since 1.0.0
 *
 */
class Big_Post_API {

	const LIVE_URL    = 'https://api.bigpost.com.au';
	const STAGING_URL = 'https://stagingapi.bigpost.com.au';

	/**
	 * API token generated against a Big Post user.
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Either 'live' or 'staging'.
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * Receives ( $message, $level ) for every request, response and failure.
	 *
	 * @var callable|null
	 */
	protected $logger;

	/**
	 * @param string        $token       API token.
	 * @param string        $environment 'live' or 'staging'.
	 * @param callable|null $logger      Optional logger callback.
	 */
	public function __construct( $token, $environment = 'live', $logger = null ) {
		$this->token       = trim( (string) $token );
		$this->environment = 'staging' === $environment ? 'staging' : 'live';
		$this->logger      = is_callable( $logger ) ? $logger : null;
	}

	/**
	 * @return string
	 */
	public function get_base_url() {
		$url = 'staging' === $this->environment ? self::STAGING_URL : self::LIVE_URL;

		return untrailingslashit( apply_filters( 'big_post_api_url', $url, $this->environment ) );
	}

	/**
	 * Suburbs whose name or postcode contains the search string.
	 *
	 * @param string $query Suburb name or postcode.
	 * @return array|WP_Error List of suburbs with lower-cased keys: postcodeid, suburb, postcode, state.
	 */
	public function get_suburbs( $query ) {
		$suburbs = $this->request( 'GET', '/api/suburbs', array( 'q' => $query ) );

		return is_wp_error( $suburbs ) ? $suburbs : $this->rows( $suburbs );
	}

	/**
	 * Depots closest to a suburb.
	 *
	 * @param string $postcode Postcode.
	 * @param string $suburb   Suburb name.
	 * @return array|WP_Error List of depots with lower-cased keys: depotid, depotname, address, suburb, postcode, state, distance.
	 */
	public function get_depots( $postcode, $suburb ) {
		$depots = $this->request(
			'GET',
			'/api/depots',
			array(
				'p' => $postcode,
				's' => $suburb,
			)
		);

		return is_wp_error( $depots ) ? $depots : $this->rows( $depots );
	}

	/**
	 * Quotes for a job.
	 *
	 * @param array $request QuoteRequest body.
	 * @return array|WP_Error The quote, with its delivery options under 'deliveryoptions'.
	 */
	public function get_quote( array $request ) {
		$response = $this->request( 'POST', '/api/getquote', array(), $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$quote  = isset( $response['object'] ) && is_array( $response['object'] ) ? $response['object'] : array();
		$errors = $this->get_error_messages( $response );

		if ( empty( $quote['deliveryoptions'] ) && $errors ) {
			return $this->error( 'big_post_quote_error', implode( ' ', $errors ) );
		}

		if ( $errors ) {
			$this->log( 'Big Post returned warnings with the quote: ' . implode( ' ', $errors ), 'warning' );
		}

		return $quote;
	}

	/**
	 * Lower-case every key so a response reads the same whether Big Post
	 * serialises it as PascalCase or camelCase.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return mixed
	 */
	public static function normalise_keys( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$normalised = array();
		foreach ( $data as $key => $value ) {
			$normalised[ is_string( $key ) ? strtolower( $key ) : $key ] = self::normalise_keys( $value );
		}

		return $normalised;
	}

	/**
	 * @param string     $method HTTP method.
	 * @param string     $path   Endpoint path.
	 * @param array      $query  Query string arguments.
	 * @param array|null $body   JSON body.
	 * @return array|WP_Error Decoded response with lower-cased keys.
	 */
	protected function request( $method, $path, array $query = array(), $body = null ) {
		$url = $this->get_base_url() . $path;
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => apply_filters( 'big_post_api_timeout', 20 ),
			'headers' => array(
				'AccessToken' => $this->token,
				'Accept'      => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$this->log( sprintf( 'Request: %s %s%s', $method, $url, isset( $args['body'] ) ? ' ' . $args['body'] : '' ) );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( 'Request failed: %s %s: %s', $method, $path, $response->get_error_message() ), 'error' );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		$this->log( sprintf( 'Response: HTTP %d %s', $code, $raw ) );

		// Big Post answers a missing or invalid token with an empty 403.
		if ( 401 === $code || 403 === $code ) {
			return $this->error(
				'big_post_unauthorized',
				__( 'Big Post rejected the API token. Check the token, and that it belongs to the selected environment.', 'big-post' ),
				$code
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			return $this->error(
				'big_post_http_error',
				/* translators: 1: HTTP status code, 2: error detail */
				sprintf( __( 'Big Post API returned HTTP %1$d: %2$s', 'big-post' ), $code, $this->describe_problem( $data, $raw ) ),
				$code
			);
		}

		if ( null === $data && '' !== trim( $raw ) ) {
			return $this->error( 'big_post_invalid_response', __( 'Big Post API returned a response that is not valid JSON.', 'big-post' ), $code );
		}

		return is_array( $data ) ? self::normalise_keys( $data ) : array();
	}

	/**
	 * Keep only the list entries that are records.
	 *
	 * @param array $data Decoded response.
	 * @return array
	 */
	protected function rows( $data ) {
		return array_values( array_filter( $data, 'is_array' ) );
	}

	/**
	 * Messages from the 'errors' list of a Big Post transfer model.
	 *
	 * @param array $response Decoded response.
	 * @return string[]
	 */
	protected function get_error_messages( $response ) {
		$messages = array();

		if ( empty( $response['errors'] ) || ! is_array( $response['errors'] ) ) {
			return $messages;
		}

		foreach ( $response['errors'] as $error ) {
			if ( is_array( $error ) && ! empty( $error['errormessage'] ) ) {
				$messages[] = (string) $error['errormessage'];
			} elseif ( is_string( $error ) && '' !== $error ) {
				$messages[] = $error;
			}
		}

		return $messages;
	}

	/**
	 * Readable detail from an RFC 9110 problem response, falling back to the raw body.
	 *
	 * @param mixed  $data Decoded body.
	 * @param string $raw  Raw body.
	 * @return string
	 */
	protected function describe_problem( $data, $raw ) {
		if ( ! is_array( $data ) ) {
			$raw = trim( wp_strip_all_tags( $raw ) );
			return '' === $raw ? __( 'no detail given', 'big-post' ) : mb_substr( $raw, 0, 300 );
		}

		// Top level only, so the field names under 'errors' keep their case.
		$data  = array_change_key_case( $data, CASE_LOWER );
		$parts = array();

		foreach ( array( 'title', 'detail' ) as $key ) {
			if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$parts[] = $data[ $key ];
			}
		}

		if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			foreach ( $data['errors'] as $field => $messages ) {
				foreach ( (array) $messages as $message ) {
					if ( is_string( $message ) ) {
						$parts[] = is_string( $field ) ? $field . ': ' . $message : $message;
					}
				}
			}
		}

		return $parts ? implode( ' ', $parts ) : __( 'no detail given', 'big-post' );
	}

	/**
	 * @param string   $code    Error code.
	 * @param string   $message Error message.
	 * @param int|null $status  HTTP status, when there is one.
	 * @return WP_Error
	 */
	protected function error( $code, $message, $status = null ) {
		$this->log( $message, 'error' );

		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * @param string $message Message.
	 * @param string $level   Log level.
	 */
	protected function log( $message, $level = 'debug' ) {
		if ( $this->logger ) {
			call_user_func( $this->logger, $message, $level );
		}
	}
}
