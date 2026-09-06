<?php
/**
 * Next.js ISR On-Demand Cache Revalidation Webhook Trigger.
 *
 * @package ApnaStay_Webhooks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApnaStay_Webhook_Trigger Class.
 */
class ApnaStay_Webhook_Trigger {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status_transition' ), 10, 3 );
	}

	/**
	 * Trigger Next.js cache revalidation when supported post types transition status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_post_status_transition( $new_status, $old_status, $post ) {
		// Supported post types for ISR cache purging.
		$supported_types = array( 'post', 'apnastay_property' );
		if ( ! in_array( $post->post_type, $supported_types, true ) ) {
			return;
		}

		// Trigger webhook if a post was published or is being unpublished/trashed/updated.
		$is_published  = ( 'publish' === $new_status );
		$was_published = ( 'publish' === $old_status );

		if ( $is_published || $was_published ) {
			// Resolve Frontend URL.
			if ( defined( 'APNASTAY_FRONTEND_URL' ) && ! empty( APNASTAY_FRONTEND_URL ) ) {
				$base_url = rtrim( APNASTAY_FRONTEND_URL, '/' );
			} else {
				$base_url = get_option( 'apnastay_frontend_url', 'https://apnastay.com' );
				$base_url = rtrim( $base_url, '/' );
			}

			$revalidate_endpoint = ( 'apnastay_property' === $post->post_type )
				? $base_url . '/api/revalidate-property'
				: $base_url . '/api/revalidate-blog';

			// Resolve Secret Key.
			if ( defined( 'APNASTAY_WEBHOOK_SECRET' ) && ! empty( APNASTAY_WEBHOOK_SECRET ) ) {
				$secret_key = APNASTAY_WEBHOOK_SECRET;
			} else {
				$secret_key = get_option( 'apnastay_webhook_secret', 'apnastay-wp-cms-secret-key-2026' );
			}

			// Perform asynchronous background post request using WordPress HTTP API.
			wp_remote_post(
				$revalidate_endpoint,
				array(
					'method'    => 'POST',
					'blocking'  => false, // Non-blocking so WordPress dashboard remains fast
					'sslverify' => true,
					'headers'   => array(
						'Content-Type'           => 'application/json',
						'x-apnastay-webhook-key' => $secret_key,
					),
					'body'      => wp_json_encode(
						array(
							'post_id'   => $post->ID,
							'post_type' => $post->post_type,
							'post_slug' => $post->post_name,
							'action'    => $new_status,
						)
					),
				)
			);
		}
	}
}
