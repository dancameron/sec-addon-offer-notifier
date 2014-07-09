<?php

class ON_Metabox {
	const PUBLISH_OPTION_NAME = 'gb_notifiy_users';

	public static function init() {	
		add_action( 'admin_init', array( get_class(), 'register_meta_boxes' ) );
	}



	public static function register_meta_boxes() {
		// Offer specific
		$args = array(
				self::PUBLISH_OPTION_NAME => array(
					'title' => 'Location Based Notifications',
					'show_callback' => array( __CLASS__, 'show_meta_box' ),
					'save_callback' => array( __CLASS__, 'save_meta_box' )
				)
			);
		do_action( 'gb_meta_box', $args, SEC_Offers::ALL_TYPES );
	}


	public static function show_meta_box( $offer, $post, $metabox ) {
		if (  !ON_Notifications::when_notifications_sent( $post->ID ) == '' ) {
			printf( '<p><label><input type="checkbox" name="%s"/> %s</label></p><p class="description">%s</p>', self::PUBLISH_OPTION_NAME, gb__( 'Send all "New Offer Published" notifications (including SMS) on update/publish.' ), gb__( 'Cannot be undone. Make sure all locations are selected.' ) );
		}
		else {
			printf( gb__( 'Notifications sent: %s.' ), date( get_option( 'date_format' ).', '.get_option( 'time_format' ), ON_Notifications::when_notifications_sent( $post->ID ) ) );
		}
	}

	public static function save_meta_box( $offer, $post_id, $post ) {
		if ( isset( $_POST[self::PUBLISH_OPTION_NAME] ) && $_POST[self::PUBLISH_OPTION_NAME] ) {
			do_action( 'sec_send_notifications_publish', $offer );
		}
	}
}
