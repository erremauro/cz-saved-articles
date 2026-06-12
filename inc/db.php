<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CZSA_DB {

	const TABLE = 'czsa_saved_articles';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id  BIGINT UNSIGNED NOT NULL,
			post_id  BIGINT UNSIGNED NOT NULL,
			saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_user_post (user_id, post_id),
			KEY idx_user (user_id),
			KEY idx_post (post_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function is_saved( int $user_id, int $post_id ): bool {
		global $wpdb;
		$table = self::table_name();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND post_id = %d LIMIT 1",
				$user_id,
				$post_id
			)
		);
	}

	public static function save( int $user_id, int $post_id ): bool {
		global $wpdb;

		$result = $wpdb->insert(
			self::table_name(),
			[
				'user_id'  => $user_id,
				'post_id'  => $post_id,
				'saved_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s' ]
		);

		return false !== $result;
	}

	public static function remove( int $user_id, int $post_id ): void {
		global $wpdb;

		$wpdb->delete(
			self::table_name(),
			[ 'user_id' => $user_id, 'post_id' => $post_id ],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Returns all saved records for a user, newest first.
	 *
	 * @return array<array{post_id: int, saved_at: int}>
	 */
	public static function get_all( int $user_id ): array {
		global $wpdb;
		$table = self::table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, UNIX_TIMESTAMP(saved_at) AS saved_at FROM {$table} WHERE user_id = %d ORDER BY saved_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	/**
	 * Migrates a single user's saved articles from usermeta to the table.
	 * Safe to call multiple times (INSERT IGNORE semantics via UNIQUE KEY).
	 */
	public static function migrate_user_from_usermeta( int $user_id ): void {
		global $wpdb;

		$records = get_user_meta( $user_id, 'czsa_saved_articles', true );
		if ( ! is_array( $records ) || empty( $records ) ) {
			return;
		}

		$table = self::table_name();

		foreach ( $records as $r ) {
			$post_id  = (int) ( $r['post_id'] ?? 0 );
			$saved_at = ! empty( $r['saved_at'] )
				? gmdate( 'Y-m-d H:i:s', (int) $r['saved_at'] )
				: current_time( 'mysql', true );

			if ( $post_id <= 0 ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (user_id, post_id, saved_at) VALUES (%d, %d, %s)",
					$user_id,
					$post_id,
					$saved_at
				)
			);
		}

		delete_user_meta( $user_id, 'czsa_saved_articles' );
	}

	/**
	 * Migrates all users who still have the legacy usermeta key.
	 */
	public static function migrate_all_from_usermeta(): void {
		global $wpdb;

		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'czsa_saved_articles'"
		);

		foreach ( $user_ids as $user_id ) {
			self::migrate_user_from_usermeta( (int) $user_id );
		}
	}
}
