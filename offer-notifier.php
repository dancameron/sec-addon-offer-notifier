<?php
/*
Plugin Name: Group Buying Addon - Deal Suggestion Advanced
Version: 1
Description: Allow registered users submit a suggested deal, choose a notification method and select a price; allows others to vote on these suggestions. Votes count down to a threshold the admin will set.
Author: Sprout Venture
Author URI: http://sproutventure.com/wordpress
Plugin Author: Dan Cameron
Text Domain: group-buying
*/


define( 'OFFER_NOTIFIER_PATH', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );
define( 'OFFER_NOTIFIER_RESOURCES_URL', plugins_url( 'resources/', __FILE__ ) );

// Load after all other plugins since we need to be compatible with groupbuyingsite
add_action( 'plugins_loaded', 'gb_suggestions_advanced' );
function gb_suggestions_advanced() {
	$gbs_min_version = '4.4';
	if ( class_exists( 'SEC_Controller' ) && version_compare( Smart_eCart::SEC_VERSION, $gbs_min_version, '>=' ) ) {
		require_once 'classes/Offer_Notifier_Addon.php.php';

		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( 'Offer_Notifier_Addon', 'sec_addon' ), 10, 1 );
	}
}
