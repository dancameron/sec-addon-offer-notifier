<?php

/**
 * Load via GBS Add-On API
 */
class Offer_Notifier_Addon extends SEC_Controller {
	const META_KEY = '_gbs_suggestions_advanced';

	public static function init() {

		// Mobile Number Registration
		require_once 'ON_Account.php';
		ON_Account::init();

		// Notifications
		require_once 'ON_Notifications.php';
		ON_Notifications::init();

		// Twilio Services
		require_once 'ON_Twilio.php';

		// Options
		require_once 'ON_Options.php';

		if ( is_admin() ) {

			require_once 'ON_Metabox.php';
			ON_Metabox::init();

			ON_Options::init();
		}


		require_once OFFER_NOTIFIER_PATH . 'lib/template-tags.php';

	}

	public static function sec_addon( $addons ) {
		$addons['offer_notifier'] = array(
			'label' => self::__( 'Offer Notifier' ),
			'description' => self::__( 'Allow registered users get notifications (email and SMS) for offers based on their location selection.' ),
			'files' => array(),
			'callbacks' => array(
				array( __CLASS__, 'init' ),
			),
			'active' => TRUE,
		);
		return $addons;
	}

}
