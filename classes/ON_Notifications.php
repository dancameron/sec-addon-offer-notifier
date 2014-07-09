<?php

class ON_Notifications extends SEC_Controller {

	const NOTIFICATION_SMS_TYPE = 'gb_sms_local_offer_notification';
	const NOTIFICATION_TYPE = 'gb_email_local_offer_notification';
	const NOTIFICATIONS_SENT = 'gb_local_offer_notifications_sent_v4h';

	public static function init() {

		// Register Notifications
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
		add_filter( 'gb_notification_shortcodes', array( get_class(), 'register_notification_shortcode' ), 10, 1 );

		// Voting hook, check for a preference
		add_action( 'gb_sa_set_vote', array( get_class(), 'maybe_add_notification_preference' ), 10, 3 );

		// Meta Box
		add_action( 'sec_send_notifications_publish', array( get_class(), 'maybe_send_notifications' ) );

	}

	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_SMS_TYPE] = array(
			'name' => self::__( 'SMS Alert: New Offer Published' ),
			'description' => self::__( "Customize the SMS notification that is sent after an offer is published in a user's preferred location." ),
			'shortcodes' => array( 'date', 'name', 'username', 'site_title', 'site_url', 'deal_url', 'deal_title', 'merchant_name' ),
			'default_title' => self::__( 'SMS Alert: New Offer Published' ),
			'default_content' => '',
			'allow_preference' => TRUE,
			'offer_specific' => FALSE
		);
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => self::__( 'E-Mail Alert: New Offer Published' ),
			'description' => self::__( "Customize the e-mail notification that is sent after an offer is published in a user's preferred location." ),
			'shortcodes' => array( 'date', 'name', 'username', 'site_title', 'site_url', 'deal_url', 'deal_title', 'merchant_name' ),
			'default_title' => self::__( 'An offer close to you is available.' ),
			'default_content' => '',
			'allow_preference' => TRUE,
			'offer_specific' => FALSE
		);
		return $notifications;
	}

	public function register_notification_shortcode( $default_shortcodes ) {
		$default_shortcodes['merchant_name'] = array(
			'description' => self::__( 'Used to return the merchant name.' ),
			'callback' => array( get_class(), 'checkout_merchant_shortcode' )
		);
		return $default_shortcodes;
	}

	public static function checkout_merchant_shortcode( $atts, $content, $code, $data ) {
		return $data['merchant_name'];
	}

	public function maybe_send_notifications( $local_offer_deal ) {
		$notify_locals = array();
		$locations = $_POST['tax_input'][gb_get_location_tax_slug()];
		foreach ( $locations as $location_id ) {
			$term = get_term_by( 'id', $location_id, gb_get_location_tax_slug() );
			if ( !is_wp_error( $term ) && isset( $term->slug ) ) {
				$notify_locals[] = $term->slug;
			}
		}

		$account_ids = array();
		foreach ( $notify_locals as $local_slug ) {
			$local_account_ids = SEC_Post_Type::find_by_meta( SEC_Account::POST_TYPE, array(
				'_'.ON_Account::LOCATION_PREF => $local_slug
			) );
			$account_ids = array_merge( $local_account_ids, $account_ids );
		}

		if ( !empty( $account_ids ) ) {
			foreach ( $account_ids as $account_id ) {
				self::send_sms_notification( $local_offer_deal, $account_id );
				self::send_notification( $local_offer_deal, $account_id );
			}
			self::mark_notifications_sent( $local_offer_deal->get_id() );
		}
		
	}

	public function mark_notifications_sent( $deal_id = 0 ) {
		update_post_meta( $deal_id, self::NOTIFICATIONS_SENT, time() );
	}

	public function when_notifications_sent( $deal_id ) {
		$meta = get_post_meta( $deal_id, self::NOTIFICATIONS_SENT, TRUE );
		return $meta;
	}

	public function has_notifications_sent( $deal_id ) {
		$meta = get_post_meta( $deal_id, self::NOTIFICATIONS_SENT, TRUE );
		return $meta != '';
	}

	///////////////////
	// Notifications //
	///////////////////
	
	public function send_sms_notification( $local_offer_deal, $account_id ) {
		// $number
		$account = SEC_Account::get_instance_by_id( $account_id );
		$user_id = $account->get_user_id();
		$number = ON_Account::get_mobile_number( $account_id );

		// $message
		$merchant_id = $local_offer_deal->get_merchant_id();
		$merchant_name = get_the_title( $merchant_id );
		$data = array(
				'user_id' => $user_id,
				'deal' => $local_offer_deal,
				'merchant_name' => $merchant_name
			);
		$message = SEC_Notifications::get_notification_content( self::NOTIFICATION_SMS_TYPE, '', $data );

		// check if disabled
		if ( SEC_Notifications::user_disabled_notification( self::NOTIFICATION_SMS_TYPE, $account ) )
			return;

		// And send
		//$sms = ON_Twilio::send_sms( $number, $message );
	}

	public function send_notification( $local_offer_deal, $account_id, $vote_data = array() ) {
		// $message
		$account = SEC_Account::get_instance_by_id( $account_id );
		$user_id = $account->get_user_id();
		$merchant_id = $local_offer_deal->get_merchant_id();
		$merchant_name = get_the_title( $merchant_id );
		$data = array(
				'user_id' => $user_id,
				'deal' => $local_offer_deal,
				'merchant_name' => $merchant_name
			);
		$to = SEC_Notifications::get_user_email( $user_id );

		// check if disabled
		if ( SEC_Notifications::user_disabled_notification( self::NOTIFICATION_TYPE, $account ) )
			return;

		SEC_Notifications::send_notification( self::NOTIFICATION_TYPE, $data, $to );
	}

	//////////////////////////////
	// Notification Preference //
	//////////////////////////////
	
	public function maybe_add_notification_preference( $user_id = 0, $local_offer_id = 0, $data = array() ) {
		if ( isset( $data['notification_preference'] ) && $data['notification_preference'] != '' ) {
			$account_id = SEC_Account::get_account_id_for_user( $user_id );
			$notifications = get_post_meta( $account_id, '_'.Group_Buying_Notifications::NOTIFICATION_SUB_OPTION, TRUE );
			// determine preference
			switch ( $data['notification_preference'] ) {
				case 'mobile':
				case 'sms':
					$notifications[] = self::NOTIFICATION_SMS_TYPE;
					// Unset the email notification since sms is the preference
					foreach ( array_keys( $notifications, self::NOTIFICATION_TYPE, true ) as $key ) {
						unset( $notifications[$key] );
					}
					break;

				case 'email':
				default:
					$notifications[] = self::NOTIFICATION_TYPE;
					// unset SMS since it's the preference
					foreach ( array_keys( $notifications, self::NOTIFICATION_SMS_TYPE, true ) as $key ) {
						unset( $notifications[$key] );
					}
					break;
			}
			update_post_meta( $account_id, '_'.Group_Buying_Notifications::NOTIFICATION_SUB_OPTION, $notifications );
		}
	}

	public function get_preference( $user_id ) {
		if ( !$user_id ) {
			$user_id = get_current_user_id();
		}
		$account_id = SEC_Account::get_account_id_for_user( $user_id );
		$notifications = get_post_meta( $account_id, '_'.Group_Buying_Notifications::NOTIFICATION_SUB_OPTION, TRUE );
		
		if ( is_array( $notifications ) ) {
			if ( array_search( self::NOTIFICATION_SMS_TYPE, $notifications ) !== false )
				return 'mobile';

			if ( array_search( self::NOTIFICATION_TYPE, $notifications ) !== false )
				return 'email';
		}

		return FALSE;
	}
}
