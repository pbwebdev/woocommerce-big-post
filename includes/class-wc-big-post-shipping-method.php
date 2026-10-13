<?php
/**
 * Big Post shipping method.
 */

defined( 'ABSPATH' ) || exit;

/**
 *
 * @since 0.0.1
 *
 */
class WC_Big_Post_Shipping_Method extends WC_Shipping_Method {

	/**
	 * @var Big_Post_API|null
	 */
	protected $api;

	/**
	 * Stops the connection check running once per registered instance.
	 *
	 * @var bool
	 */
	protected static $connection_checked = false;

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'big_post';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Big Post', 'big-post' );
		$this->method_description = __( 'Live freight quotes from Big Post. Enter your API token and pickup location here, then add Big Post to a shipping zone that covers Australia.', 'big-post' );

		$this->supports = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
			'settings',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title      = $this->get_option( 'title', $this->method_title );
		$this->tax_status = $this->get_option( 'tax_status', 'taxable' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Big Post packaging types, keyed by the API's ItemType value.
	 *
	 * @return array
	 */
	public static function get_item_types() {
		return array(
			0 => __( 'Carton', 'big-post' ),
			1 => __( 'Skid', 'big-post' ),
			2 => __( 'Pallet', 'big-post' ),
			3 => __( 'Pack', 'big-post' ),
			4 => __( 'Crate', 'big-post' ),
			5 => __( 'Roll', 'big-post' ),
			6 => __( 'Satchel', 'big-post' ),
			7 => __( 'Stillage', 'big-post' ),
			8 => __( 'Tube', 'big-post' ),
			9 => __( 'Bag', 'big-post' ),
		);
	}

	/**
	 * Delivery types, keyed by the lower-cased JobType of a quoted delivery option.
	 *
	 * @return array
	 */
	public static function get_delivery_type_labels() {
		return array(
			'hds'      => __( 'Home delivery', 'big-post' ),
			'business' => __( 'Business delivery', 'big-post' ),
			'depot'    => __( 'Depot collection', 'big-post' ),
		);
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'api_token'       => array(
				'title'       => __( 'API Token', 'big-post' ),
				'type'        => 'password',
				'description' => __( 'Your Big Post API token. Request one from Big Post on 03 9544 5525 or helpdesk@bigpost.com.au.', 'big-post' ),
				'default'     => '',
			),
			'environment'     => array(
				'title'       => __( 'Environment', 'big-post' ),
				'type'        => 'select',
				'description' => __( 'Staging needs a staging token from Big Post.', 'big-post' ),
				'default'     => 'live',
				'options'     => array(
					'live'    => __( 'Live', 'big-post' ),
					'staging' => __( 'Staging', 'big-post' ),
				),
			),
			'pickup'          => array(
				'title'       => __( 'Pickup Location', 'big-post' ),
				'type'        => 'title',
				'description' => __( 'Where Big Post collects your freight. Leave a field blank to use the store address from WooCommerce &gt; Settings &gt; General.', 'big-post' ),
			),
			'pickup_name'     => array(
				'title'   => __( 'Business Name', 'big-post' ),
				'type'    => 'text',
				'default' => '',
			),
			'pickup_address'  => array(
				'title'   => __( 'Street Address', 'big-post' ),
				'type'    => 'text',
				'default' => '',
			),
			'pickup_suburb'   => array(
				'title'   => __( 'Suburb', 'big-post' ),
				'type'    => 'text',
				'default' => '',
			),
			'pickup_state'    => array(
				'title'   => __( 'State', 'big-post' ),
				'type'    => 'select',
				'default' => '',
				'options' => array(
					''    => __( 'Use store address', 'big-post' ),
					'ACT' => __( 'Australian Capital Territory', 'big-post' ),
					'NSW' => __( 'New South Wales', 'big-post' ),
					'NT'  => __( 'Northern Territory', 'big-post' ),
					'QLD' => __( 'Queensland', 'big-post' ),
					'SA'  => __( 'South Australia', 'big-post' ),
					'TAS' => __( 'Tasmania', 'big-post' ),
					'VIC' => __( 'Victoria', 'big-post' ),
					'WA'  => __( 'Western Australia', 'big-post' ),
				),
			),
			'pickup_postcode' => array(
				'title'   => __( 'Postcode', 'big-post' ),
				'type'    => 'text',
				'default' => '',
				'css'     => 'width:100px;',
			),
			'items'           => array(
				'title'       => __( 'Items', 'big-post' ),
				'type'        => 'title',
				'description' => __( 'Each cart line is quoted as one item using the weight and dimensions of its product.', 'big-post' ),
			),
			'item_type'       => array(
				'title'       => __( 'Packaging Type', 'big-post' ),
				'type'        => 'select',
				'description' => __( 'How your products are packed for freight.', 'big-post' ),
				'default'     => '0',
				'options'     => self::get_item_types(),
				'desc_tip'    => true,
			),
			'consolidatable'  => array(
				'title'       => __( 'Consolidation', 'big-post' ),
				'type'        => 'checkbox',
				'label'       => __( 'Items can be consolidated', 'big-post' ),
				'default'     => 'no',
				'description' => __( 'Lets Big Post combine the items of an order into fewer packages when it quotes.', 'big-post' ),
			),
			'default_weight'  => array(
				'title'       => __( 'Default Weight (kg)', 'big-post' ),
				'type'        => 'decimal',
				'default'     => '0.5',
				'description' => __( 'Used for products that have no weight.', 'big-post' ),
				'css'         => 'width:100px;',
				'desc_tip'    => true,
			),
			'default_length'  => array(
				'title'       => __( 'Default Length (cm)', 'big-post' ),
				'type'        => 'decimal',
				'default'     => '10',
				'description' => __( 'Used for products that have no length.', 'big-post' ),
				'css'         => 'width:100px;',
				'desc_tip'    => true,
			),
			'default_width'   => array(
				'title'       => __( 'Default Width (cm)', 'big-post' ),
				'type'        => 'decimal',
				'default'     => '5',
				'description' => __( 'Used for products that have no width.', 'big-post' ),
				'css'         => 'width:100px;',
				'desc_tip'    => true,
			),
			'default_height'  => array(
				'title'       => __( 'Default Height (cm)', 'big-post' ),
				'type'        => 'decimal',
				'default'     => '5',
				'description' => __( 'Used for products that have no height.', 'big-post' ),
				'css'         => 'width:100px;',
				'desc_tip'    => true,
			),
			'debugging'       => array(
				'title' => __( 'Troubleshooting', 'big-post' ),
				'type'  => 'title',
			),
			'debug_mode'      => array(
				'title'       => __( 'Debug Log', 'big-post' ),
				'type'        => 'checkbox',
				'label'       => __( 'Log API requests and responses', 'big-post' ),
				'default'     => 'no',
				'description' => __( 'Written to WooCommerce &gt; Status &gt; Logs under the big-post source. Errors are always logged.', 'big-post' ),
			),
		);

		$this->instance_form_fields = array(
			'title'              => array(
				'title'       => __( 'Method Title', 'big-post' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers in front of each delivery option.', 'big-post' ),
				'default'     => __( 'Big Post', 'big-post' ),
				'desc_tip'    => true,
			),
			'delivery_types'     => array(
				'title'       => __( 'Delivery Types', 'big-post' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'The delivery types offered to customers.', 'big-post' ),
				'default'     => array_keys( self::get_delivery_type_labels() ),
				'options'     => self::get_delivery_type_labels(),
				'desc_tip'    => true,
			),
			'buyer_type'         => array(
				'title'       => __( 'Deliver To', 'big-post' ),
				'type'        => 'select',
				'description' => __( 'Whether Big Post quotes delivery to a home or to a business.', 'big-post' ),
				'default'     => 'residential',
				'options'     => array(
					'residential' => __( 'Residential addresses', 'big-post' ),
					'business'    => __( 'Business addresses', 'big-post' ),
					'auto'        => __( 'Business when the customer enters a company name', 'big-post' ),
				),
				'desc_tip'    => true,
			),
			'buyer_has_forklift' => array(
				'title'   => __( 'Forklift', 'big-post' ),
				'type'    => 'checkbox',
				'label'   => __( 'Business addresses can unload with a forklift', 'big-post' ),
				'default' => 'no',
			),
			'authority_to_leave' => array(
				'title'   => __( 'Authority To Leave', 'big-post' ),
				'type'    => 'checkbox',
				'label'   => __( 'Include services that are left without a signature', 'big-post' ),
				'default' => 'no',
			),
			'rate_display'       => array(
				'title'   => __( 'Rates Shown', 'big-post' ),
				'type'    => 'select',
				'default' => 'cheapest',
				'options' => array(
					'cheapest' => __( 'Cheapest carrier for each delivery type', 'big-post' ),
					'all'      => __( 'Every carrier', 'big-post' ),
				),
			),
			'show_carrier'       => array(
				'title'   => __( 'Carrier Name', 'big-post' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show the carrier name to customers', 'big-post' ),
				'default' => 'yes',
			),
			'show_eta'           => array(
				'title'   => __( 'Delivery Estimate', 'big-post' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show the estimated delivery date to customers', 'big-post' ),
				'default' => 'no',
			),
			'margin'             => array(
				'title'       => __( 'Margin', 'big-post' ),
				'type'        => 'text',
				'description' => __( 'Added to every quote. Enter an amount such as 5.50, or a percentage such as 10%. Use a negative value to discount.', 'big-post' ),
				'default'     => '',
				'css'         => 'width:100px;',
				'desc_tip'    => true,
			),
			'tax_status'         => array(
				'title'       => __( 'Tax Status', 'big-post' ),
				'type'        => 'select',
				'description' => __( 'Taxable charges the quote excluding GST and lets WooCommerce add tax. None charges the GST inclusive quote.', 'big-post' ),
				'default'     => 'taxable',
				'options'     => array(
					'taxable' => __( 'Taxable', 'big-post' ),
					'none'    => _x( 'None', 'Tax status', 'big-post' ),
				),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Accepts an amount or a percentage, with an optional leading minus.
	 *
	 * @param string $key   Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_margin_field( $key, $value ) {
		$value = str_replace( ' ', '', (string) $value );

		return preg_match( '/^-?\d+(\.\d+)?%?$/', $value ) ? $value : '';
	}

	/**
	 * Saves the settings, then confirms the token and pickup location with Big Post.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		if ( ! $this->instance_id && ! self::$connection_checked ) {
			self::$connection_checked = true;
			$this->check_connection();
		}

		return $saved;
	}

	public function is_available( $package ) {
		$available = $this->is_enabled()
			&& isset( $package['destination']['country'] )
			&& 'AU' === $package['destination']['country']
			&& '' !== $this->get_api_token();

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this );
	}

	public function calculate_shipping( $package = array() ) {
		$api = $this->get_api();
		if ( ! $api ) {
			$this->log( 'No quote requested: the Big Post API token is missing.', 'error' );
			return;
		}

		$items = $this->get_quote_items( $package );
		if ( empty( $items ) ) {
			return;
		}

		$pickup = $this->get_pickup_location( $api );
		if ( is_wp_error( $pickup ) ) {
			$this->log( 'No quote requested: ' . $pickup->get_error_message(), 'error' );
			return;
		}

		$buyer = $this->get_buyer_location( $api, $package );
		if ( is_wp_error( $buyer ) ) {
			$this->log( 'No quote requested: ' . $buyer->get_error_message() );
			return;
		}

		$is_business = $this->is_business_buyer( $package );

		$request = array(
			'BuyerIsBusiness'               => $is_business,
			'BuyerHasForklift'              => $is_business && 'yes' === $this->get_option( 'buyer_has_forklift', 'no' ),
			'ReturnAuthorityToLeaveOptions' => 'yes' === $this->get_option( 'authority_to_leave', 'no' ),
			'PickupLocation'                => $pickup,
			'BuyerLocation'                 => $buyer,
			'Items'                         => $items,
		);

		$depot = null;
		if ( in_array( 'depot', $this->get_delivery_types(), true ) ) {
			$depot = $this->get_nearest_depot( $api, $buyer['Locality'] );
			if ( $depot ) {
				$request['DepotId'] = (int) $depot['depotid'];
			}
		}

		$request = apply_filters( 'big_post_quote_request', $request, $package, $this );

		$quote = $this->get_quote( $api, $request );
		if ( is_wp_error( $quote ) ) {
			return;
		}

		foreach ( $this->build_rates( $quote, $depot ) as $rate ) {
			$rate['package'] = $package;
			$this->add_rate( $rate );
		}
	}

	/**
	 * @return string
	 */
	protected function get_api_token() {
		return trim( (string) $this->get_option( 'api_token', '' ) );
	}

	/**
	 * @return Big_Post_API|null Null when no token is saved.
	 */
	protected function get_api() {
		if ( null === $this->api && '' !== $this->get_api_token() ) {
			$this->api = new Big_Post_API( $this->get_api_token(), $this->get_option( 'environment', 'live' ), array( $this, 'log' ) );
		}

		return $this->api;
	}

	/**
	 * @return string[]
	 */
	protected function get_delivery_types() {
		$types = $this->get_option( 'delivery_types', array_keys( self::get_delivery_type_labels() ) );

		return is_array( $types ) ? $types : array();
	}

	/**
	 * @param array $package Shipping package.
	 * @return bool
	 */
	protected function is_business_buyer( $package ) {
		$buyer_type = $this->get_option( 'buyer_type', 'residential' );

		if ( 'auto' === $buyer_type ) {
			$company     = isset( $package['destination']['company'] ) ? $package['destination']['company'] : '';
			$is_business = '' !== trim( (string) $company );
		} else {
			$is_business = 'business' === $buyer_type;
		}

		return (bool) apply_filters( 'big_post_buyer_is_business', $is_business, $package, $this );
	}

	/**
	 * The cart lines of a package as Big Post items, in centimetres and kilograms.
	 *
	 * @param array $package Shipping package.
	 * @return array
	 */
	protected function get_quote_items( $package ) {
		$items          = array();
		$item_type      = (int) $this->get_option( 'item_type', 0 );
		$consolidatable = 'yes' === $this->get_option( 'consolidatable', 'no' );

		foreach ( $package['contents'] as $values ) {
			$product  = $values['data'];
			$quantity = (int) $values['quantity'];

			if ( $quantity < 1 || ! $product->needs_shipping() ) {
				continue;
			}

			$description = trim( wp_strip_all_tags( $product->get_name() ) );

			$item = array(
				'ItemType'       => $item_type,
				'Description'    => '' === $description ? __( 'Item', 'big-post' ) : mb_substr( $description, 0, 50 ),
				'Quantity'       => $quantity,
				'Length'         => $this->get_dimension( $product->get_length(), 'default_length' ),
				'Width'          => $this->get_dimension( $product->get_width(), 'default_width' ),
				'Height'         => $this->get_dimension( $product->get_height(), 'default_height' ),
				'Weight'         => $this->get_weight( $product->get_weight() ),
				'Consolidatable' => $consolidatable,
			);

			$item = apply_filters( 'big_post_quote_item', $item, $product, $values, $this );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * @param mixed  $value       Product dimension in the store's unit.
	 * @param string $default_key Setting holding the fallback in centimetres.
	 * @return float Centimetres.
	 */
	protected function get_dimension( $value, $default_key ) {
		$value = (float) $value;
		$value = $value > 0 ? (float) wc_get_dimension( $value, 'cm' ) : (float) $this->get_option( $default_key, 0 );

		return round( $value, 2 );
	}

	/**
	 * @param mixed $value Product weight in the store's unit.
	 * @return float Kilograms.
	 */
	protected function get_weight( $value ) {
		$value = (float) $value;
		$value = $value > 0 ? (float) wc_get_weight( $value, 'kg' ) : (float) $this->get_option( 'default_weight', 0 );

		return round( $value, 3 );
	}

	/**
	 * @param Big_Post_API $api    API client.
	 * @param bool         $cached Whether an earlier locality lookup may be reused.
	 * @return array|WP_Error ApiLocationModel for the pickup address.
	 */
	protected function get_pickup_location( $api, $cached = true ) {
		$base = wc_get_base_location();

		$name     = $this->get_setting_or( 'pickup_name', get_bloginfo( 'name' ) );
		$address  = $this->get_setting_or( 'pickup_address', get_option( 'woocommerce_store_address', '' ) );
		$suburb   = $this->get_setting_or( 'pickup_suburb', get_option( 'woocommerce_store_city', '' ) );
		$postcode = $this->get_setting_or( 'pickup_postcode', get_option( 'woocommerce_store_postcode', '' ) );
		$state    = $this->get_setting_or( 'pickup_state', isset( $base['state'] ) ? $base['state'] : '' );

		if ( '' === $suburb && '' === $postcode ) {
			return new WP_Error( 'big_post_no_pickup', __( 'The Big Post pickup location needs a suburb and postcode.', 'big-post' ) );
		}

		$locality = $this->find_locality( $api, $suburb, $postcode, $state, $cached );
		if ( is_wp_error( $locality ) ) {
			return $locality;
		}

		return array(
			'Name'       => '' === $name ? __( 'Store', 'big-post' ) : $name,
			'Address'    => '' === $address ? $locality['Suburb'] . ' ' . $locality['State'] : $address,
			'LocalityId' => $locality['Id'],
			'Locality'   => $locality,
		);
	}

	/**
	 * Only the suburb, state and postcode of the customer are sent to Big Post for a quote.
	 *
	 * @param Big_Post_API $api     API client.
	 * @param array        $package Shipping package.
	 * @return array|WP_Error ApiLocationModel for the delivery address.
	 */
	protected function get_buyer_location( $api, $package ) {
		$destination = $package['destination'];

		$suburb   = isset( $destination['city'] ) ? trim( (string) $destination['city'] ) : '';
		$postcode = isset( $destination['postcode'] ) ? trim( (string) $destination['postcode'] ) : '';
		$state    = isset( $destination['state'] ) ? trim( (string) $destination['state'] ) : '';

		if ( '' === $postcode ) {
			return new WP_Error( 'big_post_no_destination', __( 'The delivery address has no postcode yet.', 'big-post' ) );
		}

		$locality = $this->find_locality( $api, $suburb, $postcode, $state );
		if ( is_wp_error( $locality ) ) {
			return $locality;
		}

		return array(
			'Name'       => __( 'Customer', 'big-post' ),
			'Address'    => $locality['Suburb'] . ' ' . $locality['State'],
			'LocalityId' => $locality['Id'],
			'Locality'   => $locality,
		);
	}

	/**
	 * Resolve a suburb to the locality Big Post knows it by.
	 *
	 * @param Big_Post_API $api      API client.
	 * @param string       $suburb   Suburb name.
	 * @param string       $postcode Postcode.
	 * @param string       $state    State code.
	 * @param bool         $cached   Whether an earlier lookup may be reused.
	 * @return array|WP_Error Locality with Id, Suburb, Postcode and State.
	 */
	protected function find_locality( $api, $suburb, $postcode, $state, $cached = true ) {
		$suburb   = $this->normalise( $suburb );
		$postcode = preg_replace( '/\D/', '', (string) $postcode );
		$state    = strtoupper( trim( (string) $state ) );

		$cache_key = 'big_post_loc_' . md5( $api->get_base_url() . '|' . $suburb . '|' . $postcode . '|' . $state );
		$locality  = $cached ? get_transient( $cache_key ) : false;
		if ( is_array( $locality ) ) {
			return $locality;
		}

		// A postcode search returns every suburb in it, so try that before the suburb name.
		foreach ( array_filter( array( $postcode, $suburb ), 'strlen' ) as $query ) {
			$suburbs = $api->get_suburbs( $query );
			if ( is_wp_error( $suburbs ) ) {
				return $suburbs;
			}

			$locality = $this->match_locality( $suburbs, $suburb, $postcode, $state );
			if ( $locality ) {
				set_transient( $cache_key, $locality, WEEK_IN_SECONDS );
				return $locality;
			}
		}

		return new WP_Error(
			'big_post_no_locality',
			/* translators: %s: suburb, state and postcode */
			sprintf( __( 'Big Post has no locality matching %s.', 'big-post' ), trim( $suburb . ' ' . $state . ' ' . $postcode ) )
		);
	}

	/**
	 * A postcode can cover several suburbs, so prefer the one the customer named
	 * and settle for another suburb in the same postcode when it is not found.
	 *
	 * @param array  $suburbs  Suburbs from the API.
	 * @param string $suburb   Normalised suburb name.
	 * @param string $postcode Digits of the postcode.
	 * @param string $state    Upper-cased state code.
	 * @return array|null
	 */
	protected function match_locality( $suburbs, $suburb, $postcode, $state ) {
		$best       = null;
		$best_score = -1;

		foreach ( $suburbs as $candidate ) {
			if ( empty( $candidate['postcodeid'] ) ) {
				continue;
			}

			$locality = array(
				'Id'       => (int) $candidate['postcodeid'],
				'Suburb'   => isset( $candidate['suburb'] ) ? (string) $candidate['suburb'] : '',
				'Postcode' => isset( $candidate['postcode'] ) ? str_pad( (string) $candidate['postcode'], 4, '0', STR_PAD_LEFT ) : '',
				'State'    => isset( $candidate['state'] ) ? strtoupper( (string) $candidate['state'] ) : '',
			);

			$same_suburb = '' !== $suburb && $this->normalise( $locality['Suburb'] ) === $suburb;

			// Without a postcode to anchor on, only the named suburb will do.
			if ( '' !== $postcode ? (int) $locality['Postcode'] !== (int) $postcode : ! $same_suburb ) {
				continue;
			}

			$score = ( $same_suburb ? 2 : 0 ) + ( '' !== $state && $locality['State'] === $state ? 1 : 0 );
			if ( $score > $best_score ) {
				$best       = $locality;
				$best_score = $score;
			}
		}

		return $best;
	}

	/**
	 * @param Big_Post_API $api      API client.
	 * @param array        $locality Delivery locality.
	 * @return array|null The closest depot, with lower-cased keys.
	 */
	protected function get_nearest_depot( $api, $locality ) {
		$cache_key = 'big_post_depot_' . md5( $api->get_base_url() . '|' . $locality['Id'] );
		$depot     = get_transient( $cache_key );
		if ( is_array( $depot ) ) {
			return $depot;
		}

		$depots = $api->get_depots( $locality['Postcode'], $locality['Suburb'] );
		if ( is_wp_error( $depots ) ) {
			return null;
		}

		$depot = null;
		foreach ( $depots as $candidate ) {
			if ( empty( $candidate['depotid'] ) ) {
				continue;
			}

			$closer = isset( $candidate['distance'], $depot['distance'] ) && (float) $candidate['distance'] < (float) $depot['distance'];
			if ( null === $depot || $closer ) {
				$depot = $candidate;
			}
		}

		if ( $depot ) {
			set_transient( $cache_key, $depot, DAY_IN_SECONDS );
		}

		return $depot;
	}

	/**
	 * @param Big_Post_API $api     API client.
	 * @param array        $request QuoteRequest body.
	 * @return array|WP_Error
	 */
	protected function get_quote( $api, $request ) {
		$ttl       = (int) apply_filters( 'big_post_quote_cache_ttl', 15 * MINUTE_IN_SECONDS );
		$cache_key = 'big_post_quote_' . md5( $api->get_base_url() . '|' . md5( $this->get_api_token() ) . '|' . wp_json_encode( $request ) );

		$quote = $ttl > 0 ? get_transient( $cache_key ) : false;
		if ( is_array( $quote ) ) {
			return $quote;
		}

		$quote = $api->get_quote( $request );
		if ( ! is_wp_error( $quote ) && $ttl > 0 ) {
			set_transient( $cache_key, $quote, $ttl );
		}

		return $quote;
	}

	/**
	 * Turn a quote into shipping rates, cheapest first.
	 *
	 * @param array      $quote Quote from the API.
	 * @param array|null $depot Depot used for depot collection, when there is one.
	 * @return array Arguments for add_rate().
	 */
	protected function build_rates( $quote, $depot ) {
		$rates    = array();
		$enabled  = $this->get_delivery_types();
		$show_all = 'all' === $this->get_option( 'rate_display', 'cheapest' );
		$options  = isset( $quote['deliveryoptions'] ) && is_array( $quote['deliveryoptions'] ) ? $quote['deliveryoptions'] : array();

		foreach ( $options as $option ) {
			$type = $this->get_delivery_type( isset( $option['jobtype'] ) ? $option['jobtype'] : '' );
			if ( ! in_array( $type, $enabled, true ) || empty( $option['carrieroptions'] ) || ! is_array( $option['carrieroptions'] ) ) {
				continue;
			}

			$type_rates = array();
			foreach ( $option['carrieroptions'] as $carrier ) {
				$rate = is_array( $carrier ) ? $this->build_rate( $type, $carrier, $depot, $show_all ) : null;
				if ( $rate ) {
					$type_rates[] = $rate;
				}
			}

			usort( $type_rates, array( $this, 'sort_rates' ) );
			$rates = array_merge( $rates, $show_all ? $type_rates : array_slice( $type_rates, 0, 1 ) );
		}

		usort( $rates, array( $this, 'sort_rates' ) );

		return $rates;
	}

	/**
	 * @param string     $type     Delivery type key.
	 * @param array      $carrier  Carrier option from the quote.
	 * @param array|null $depot    Depot used for depot collection.
	 * @param bool       $show_all Whether every carrier is being listed.
	 * @return array|null Arguments for add_rate(), or null when the option has no price.
	 */
	protected function build_rate( $type, $carrier, $depot, $show_all ) {
		$cost = $this->get_cost( $carrier );
		if ( null === $cost ) {
			return null;
		}

		$labels       = self::get_delivery_type_labels();
		$carrier_id   = isset( $carrier['carrierid'] ) ? (string) $carrier['carrierid'] : '';
		$carrier_name = isset( $carrier['carriername'] ) ? trim( (string) $carrier['carriername'] ) : '';
		$service_code = isset( $carrier['servicecode'] ) ? trim( (string) $carrier['servicecode'] ) : '';
		$leave        = ! empty( $carrier['requiresauthoritytoleave'] );
		$eta          = $this->format_eta( isset( $carrier['eta'] ) ? $carrier['eta'] : '' );
		$depot_name   = 'depot' === $type && $depot ? $this->describe_depot( $depot, false ) : '';

		$label = $this->title . ' - ' . $labels[ $type ];
		if ( '' !== $depot_name ) {
			$label .= ' (' . $depot_name . ')';
		}
		if ( '' !== $carrier_name && ( $show_all || 'yes' === $this->get_option( 'show_carrier', 'yes' ) ) ) {
			$label .= ' - ' . $carrier_name;
		}
		if ( $leave ) {
			$label .= ' - ' . __( 'authority to leave', 'big-post' );
		}
		if ( '' !== $eta && 'yes' === $this->get_option( 'show_eta', 'no' ) ) {
			/* translators: %s: estimated delivery date */
			$label .= ' - ' . sprintf( __( 'ETA %s', 'big-post' ), $eta );
		}

		$meta_data = array_filter(
			array(
				__( 'Delivery type', 'big-post' )      => $labels[ $type ],
				__( 'Carrier', 'big-post' )            => $carrier_name,
				__( 'Carrier ID', 'big-post' )         => $carrier_id,
				__( 'Service code', 'big-post' )       => $service_code,
				__( 'Depot', 'big-post' )              => 'depot' === $type && $depot ? $this->describe_depot( $depot, true ) : '',
				__( 'ETA', 'big-post' )                => $eta,
				__( 'Authority to leave', 'big-post' ) => $leave ? __( 'Yes', 'big-post' ) : '',
			),
			'strlen'
		);

		// One stable ID per delivery type keeps the customer's choice selected when the cheapest carrier changes.
		$suffix = $show_all ? $type . '_' . $carrier_id . '_' . $service_code . ( $leave ? '_atl' : '' ) : $type;

		return array(
			'id'        => $this->get_rate_id( sanitize_key( $suffix ) ),
			'label'     => $label,
			'cost'      => $cost,
			'meta_data' => $meta_data,
		);
	}

	/**
	 * The price charged for a carrier option, after margin.
	 *
	 * @param array $carrier Carrier option from the quote.
	 * @return float|null Null when the option has no price.
	 */
	protected function get_cost( $carrier ) {
		$charge = isset( $carrier['charge'] ) && is_numeric( $carrier['charge'] ) ? (float) $carrier['charge'] : null;
		$total  = isset( $carrier['total'] ) && is_numeric( $carrier['total'] ) ? (float) $carrier['total'] : null;
		$tax    = isset( $carrier['tax'] ) && is_numeric( $carrier['tax'] ) ? (float) $carrier['tax'] : 0.0;

		if ( null === $charge && null === $total ) {
			return null;
		}

		// WooCommerce adds the tax itself for a taxable method, so give it the charge before GST.
		if ( 'taxable' === $this->tax_status && wc_tax_enabled() ) {
			$cost = null !== $charge ? $charge : $total - $tax;
		} else {
			$cost = null !== $total ? $total : $charge + $tax;
		}

		$margin = (string) $this->get_option( 'margin', '' );
		if ( '' !== $margin ) {
			$cost += (float) $this->get_fee( $margin, $cost );
		}

		//info: shipping rate filter to allow users to modify the shipping price.
		$cost = (float) apply_filters( 'big_post_shipping_rate', $cost, $carrier, $this );

		return max( 0.0, round( $cost, 2 ) );
	}

	/**
	 * sort rates based on cost
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
	public function sort_rates( $a, $b ) {
		if ( $a['cost'] == $b['cost'] ) {
			return 0;
		}
		return ( $a['cost'] < $b['cost'] ) ? -1 : 1;
	}

	/**
	 * @param mixed $job_type JobType of a delivery option.
	 * @return string Delivery type key, or an empty string when it is not recognised.
	 */
	protected function get_delivery_type( $job_type ) {
		$numeric = array(
			1 => 'depot',
			2 => 'business',
			3 => 'hds',
		);

		if ( is_numeric( $job_type ) ) {
			return isset( $numeric[ (int) $job_type ] ) ? $numeric[ (int) $job_type ] : '';
		}

		$job_type = strtolower( trim( (string) $job_type ) );

		return array_key_exists( $job_type, self::get_delivery_type_labels() ) ? $job_type : '';
	}

	/**
	 * @param array $depot Depot from the API.
	 * @param bool  $full  Whether to include the street address.
	 * @return string
	 */
	protected function describe_depot( $depot, $full ) {
		$keys = $full ? array( 'depotname', 'address', 'suburb', 'state', 'postcode' ) : array( 'depotname', 'suburb' );

		$parts = array();
		foreach ( $keys as $key ) {
			$value = isset( $depot[ $key ] ) ? trim( (string) $depot[ $key ] ) : '';
			if ( '' !== $value && ! in_array( $value, $parts, true ) ) {
				$parts[] = $value;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * @param mixed $eta Estimated delivery date from the API.
	 * @return string The date in the store's format, or an empty string when there is none.
	 */
	protected function format_eta( $eta ) {
		$timestamp = is_string( $eta ) && '' !== trim( $eta ) ? strtotime( $eta ) : false;

		return $timestamp && $timestamp > 0 ? date_i18n( wc_date_format(), $timestamp ) : '';
	}

	/**
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value used when the setting is blank.
	 * @return string
	 */
	protected function get_setting_or( $key, $fallback ) {
		$value = trim( (string) $this->get_option( $key, '' ) );

		return '' === $value ? trim( (string) $fallback ) : $value;
	}

	/**
	 * @param string $value Address part.
	 * @return string Trimmed, single-spaced and lower-cased.
	 */
	protected function normalise( $value ) {
		return strtolower( trim( preg_replace( '/\s+/', ' ', (string) $value ) ) );
	}

	/**
	 * Report whether Big Post accepts the saved token and knows the pickup location.
	 */
	protected function check_connection() {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			return;
		}

		$this->api = null;
		$api       = $this->get_api();

		if ( ! $api ) {
			WC_Admin_Settings::add_error( __( 'Big Post: enter your API token to start quoting.', 'big-post' ) );
			return;
		}

		// A cached pickup locality would hide a token that no longer works.
		$pickup = $this->get_pickup_location( $api, false );

		if ( is_wp_error( $pickup ) ) {
			WC_Admin_Settings::add_error( 'Big Post: ' . $pickup->get_error_message() );
			return;
		}

		WC_Admin_Settings::add_message(
			sprintf(
				/* translators: %s: suburb, state and postcode */
				__( 'Big Post: connected. Freight will be quoted from %s.', 'big-post' ),
				$pickup['Locality']['Suburb'] . ' ' . $pickup['Locality']['State'] . ' ' . $pickup['Locality']['Postcode']
			)
		);
	}

	/**
	 * Errors are always logged; everything else only in debug mode.
	 *
	 * @param string $message Message.
	 * @param string $level   Log level.
	 */
	public function log( $message, $level = 'debug' ) {
		if ( 'debug' === $level && 'yes' !== $this->get_option( 'debug_mode', 'no' ) ) {
			return;
		}

		wc_get_logger()->log( $level, $message, array( 'source' => 'big-post' ) );
	}
}
