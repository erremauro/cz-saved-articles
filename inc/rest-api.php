<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZSA_REST {

	const NAMESPACE = 'cz-saved-articles/v1';

	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/toggle', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'toggle' ],
			'permission_callback' => [ __CLASS__, 'require_login' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	public static function require_login() {
		return is_user_logged_in();
	}

	public static function toggle( WP_REST_Request $req ) {
		$user_id = get_current_user_id();
		$post_id = (int) $req->get_param( 'post_id' );

		$post_obj = get_post( $post_id );
		if ( ! $post_obj || 'post' !== $post_obj->post_type ) {
			return new WP_Error( 'invalid_post', 'Post not found', [ 'status' => 404 ] );
		}

		if ( CZSA_DB::is_saved( $user_id, $post_id ) ) {
			CZSA_DB::remove( $user_id, $post_id );
			$is_saved = false;
		} else {
			CZSA_DB::save( $user_id, $post_id );
			$is_saved = true;
		}

		return rest_ensure_response( [ 'saved' => $is_saved, 'post_id' => $post_id ] );
	}
}
