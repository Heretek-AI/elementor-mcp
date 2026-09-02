<?php
/**
 * Plugin Name: EMCP Tools Connector
 * Description: Standalone bridge for remote site push/pull migrations with EMCP Tools Pro.
 * Version: 1.0.0
 * Author: Heretek AI
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', function() {
	register_rest_route( 'emcp-connector/v1', '/status', array(
		'methods'             => 'GET',
		'callback'            => function() {
			return rest_ensure_response( array( 'active' => true, 'site' => home_url(), 'version' => '1.0.0' ) );
		},
		'permission_callback' => '__return_true',
	) );
} );
