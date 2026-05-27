<?php
/**
 * Plugin Name: CZ Saved Articles
 * Description: Salva articoli preferiti con un segnalibro. Solo per utenti registrati.
 * Version:     1.0.0
 * Author:      CZ
 * Text Domain: cz-saved-articles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CZSA_VERSION', '1.0.0' );
define( 'CZSA_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CZSA_URL',     plugins_url( '', __FILE__ ) . '/' );

require_once CZSA_PATH . 'inc/rest-api.php';

register_activation_hook( __FILE__, [ 'CZ_Saved_Articles', 'activate' ] );

final class CZ_Saved_Articles {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		self::ensure_saved_page();
	}

	private static function ensure_saved_page() {
		$page_id = (int) get_option( 'czsa_saved_page_id', 0 );
		if ( $page_id ) {
			$page = get_post( $page_id );
			if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
				return;
			}
		}

		$page_id = wp_insert_post( [
			'post_title'   => 'Articoli Salvati',
			'post_name'    => 'articoli-salvati',
			'post_content' => '[czsa_saved_articles]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		] );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'czsa_saved_page_id', $page_id );
		}
	}

	private function __construct() {
		add_action( 'rest_api_init',           [ 'CZSA_REST', 'register_routes' ] );
		add_action( 'wp_enqueue_scripts',      [ $this, 'enqueue_assets' ] );
		add_action( 'czh_nav_user_menu_items', [ $this, 'render_nav_menu_item' ], 9 );
		add_shortcode( 'czsa_saved_articles',  [ $this, 'render_shortcode' ] );
	}

	private function get_saved_page_url() {
		$page_id = (int) get_option( 'czsa_saved_page_id', 0 );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) return $url;
		}
		return home_url( '/articoli-salvati' );
	}

	private function is_saved_page() {
		global $post;
		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'czsa_saved_articles' );
	}

	/** ---- Assets ---- */

	public function enqueue_assets() {
		$is_article    = is_singular( 'post' );
		$is_saved_page = $this->is_saved_page();
		$logged_in     = is_user_logged_in();

		if ( ! $is_article && ! $is_saved_page ) {
			return;
		}

		if ( $is_saved_page && ! $logged_in ) {
			return;
		}

		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

		$get_asset = function ( $rel ) use ( $debug ) {
			$min_rel = preg_replace( '/\.(js|css)$/', '.min.$1', $rel );
			$use_rel = ( ! $debug && file_exists( CZSA_PATH . ltrim( $min_rel, '/' ) ) ) ? $min_rel : $rel;
			$abs     = CZSA_PATH . ltrim( $use_rel, '/' );
			$url     = CZSA_URL . ltrim( $use_rel, '/' );
			$ver     = file_exists( $abs ) ? filemtime( $abs ) : CZSA_VERSION;
			return [ $url, $ver ];
		};

		list( $js_url, $js_ver ) = $get_asset( 'assets/js/czsa.js' );
		wp_register_script( 'czsa-js', $js_url, [], $js_ver, true );

		global $post;
		$is_saved = $logged_in && $is_article && $post instanceof WP_Post
			? $this->is_post_saved( get_current_user_id(), $post->ID )
			: false;

		if ( $logged_in ) {
			$config = [
				'version'  => CZSA_VERSION,
				'rest'     => [
					'root'  => esc_url_raw( trailingslashit( rest_url( CZSA_REST::NAMESPACE ) ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				],
				'user'     => [
					'loggedIn' => true,
					'id'       => get_current_user_id(),
				],
				'context'  => [
					'type'   => $is_article ? 'post' : 'saved-page',
					'postId' => $is_article && $post instanceof WP_Post ? (int) $post->ID : null,
					'isSaved' => $is_saved,
				],
				'i18n'     => [
					'save'           => __( 'Salva articolo', 'cz-saved-articles' ),
					'saved'          => __( 'Articolo salvato', 'cz-saved-articles' ),
					'empty'          => __( 'Nessun articolo salvato ancora.', 'cz-saved-articles' ),
					'error'          => __( 'Errore nel caricamento.', 'cz-saved-articles' ),
					'confirm_remove' => __( 'Vuoi rimuovere questo articolo?', 'cz-saved-articles' ),
					'remove'         => __( 'Elimina', 'cz-saved-articles' ),
					'cancel'         => __( 'Annulla', 'cz-saved-articles' ),
					'no_results'     => __( 'Nessun risultato trovato.', 'cz-saved-articles' ),
				],
			];
		} else {
			// Guest on article page: show bookmark button that redirects to login
			$config = [
				'version'  => CZSA_VERSION,
				'user'     => [ 'loggedIn' => false, 'id' => 0 ],
				'loginUrl' => wp_login_url( get_permalink() ),
				'context'  => [
					'type'    => 'post',
					'postId'  => $post instanceof WP_Post ? (int) $post->ID : null,
					'isSaved' => false,
				],
				'i18n'     => [
					'save'           => __( 'Salva articolo', 'cz-saved-articles' ),
					'saved'          => __( 'Articolo salvato', 'cz-saved-articles' ),
					'empty'          => __( 'Nessun articolo salvato ancora.', 'cz-saved-articles' ),
					'error'          => __( 'Errore nel caricamento.', 'cz-saved-articles' ),
					'confirm_remove' => __( 'Vuoi rimuovere questo articolo?', 'cz-saved-articles' ),
					'remove'         => __( 'Elimina', 'cz-saved-articles' ),
					'cancel'         => __( 'Annulla', 'cz-saved-articles' ),
					'no_results'     => __( 'Nessun risultato trovato.', 'cz-saved-articles' ),
				],
			];
		}

		wp_enqueue_script( 'czsa-js' );
		wp_add_inline_script( 'czsa-js', 'window.CZSA = ' . wp_json_encode( $config ) . ';', 'before' );

		list( $css_url, $css_ver ) = $get_asset( 'assets/css/czsa.css' );
		wp_enqueue_style( 'czsa', $css_url, [], $css_ver );
	}

	/** ---- Nav user menu item ---- */

	public function render_nav_menu_item() {
		$svg = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
		?>
		<a role="menuitem" href="<?php echo esc_url( $this->get_saved_page_url() ); ?>">
			<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php esc_html_e( 'Articoli Salvati', 'cz-saved-articles' ); ?></span>
		</a>
		<?php
	}

	/** ---- Shortcode ---- */

	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Accedi per vedere i tuoi articoli salvati.', 'cz-saved-articles' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$records = $this->get_saved_records( $user_id );

		if ( empty( $records ) ) {
			return '<div id="czsa-saved-app" class="czsa-saved-app"><p class="czsa-empty">' . esc_html__( 'Nessun articolo salvato ancora.', 'cz-saved-articles' ) . '</p></div>';
		}

		// Most recently saved first
		$records = array_reverse( $records );

		$items = [];
		foreach ( $records as $record ) {
			$post_id  = (int) ( $record['post_id'] ?? 0 );
			$post_obj = get_post( $post_id );
			if ( ! $post_obj || 'post' !== $post_obj->post_type || 'publish' !== $post_obj->post_status ) {
				continue;
			}

			$items[] = [
				'post_id'     => $post_obj->ID,
				'title'       => get_the_title( $post_obj ),
				'link'        => get_permalink( $post_obj ),
				'author'      => get_the_author_meta( 'display_name', $post_obj->post_author ),
				'volume_name' => $this->get_volume_name_for_post( $post_obj->ID ),
				'saved_at'    => ! empty( $record['saved_at'] ) ? date_i18n( 'j M Y', (int) $record['saved_at'] ) : null,
			];
		}

		if ( empty( $items ) ) {
			return '<div id="czsa-saved-app" class="czsa-saved-app"><p class="czsa-empty">' . esc_html__( 'Nessun articolo salvato ancora.', 'cz-saved-articles' ) . '</p></div>';
		}

		ob_start();
		?>
		<div id="czsa-saved-app" class="czsa-saved-app">
			<h1 class="czsa-page-title"><?php esc_html_e( 'Articoli Salvati', 'cz-saved-articles' ); ?></h1>
			<div class="czsa-toolbar">
				<input type="search" class="czsa-toolbar__search"
					placeholder="<?php esc_attr_e( 'Cerca per titolo, autore, volume…', 'cz-saved-articles' ); ?>"
					aria-label="<?php esc_attr_e( 'Cerca articoli salvati', 'cz-saved-articles' ); ?>">
				<select class="czsa-toolbar__order" aria-label="<?php esc_attr_e( 'Ordina per', 'cz-saved-articles' ); ?>">
					<option value="date"><?php esc_html_e( 'Data di aggiunta', 'cz-saved-articles' ); ?></option>
					<option value="asc"><?php esc_html_e( 'A → Z', 'cz-saved-articles' ); ?></option>
					<option value="desc"><?php esc_html_e( 'Z → A', 'cz-saved-articles' ); ?></option>
				</select>
			</div>
			<ul class="czsa-list">
				<?php foreach ( $items as $item ) : ?>
				<li class="czsa-item"
					data-post-id="<?php echo esc_attr( $item['post_id'] ); ?>"
					data-title="<?php echo esc_attr( $item['title'] ); ?>"
					data-author="<?php echo esc_attr( $item['author'] ?? '' ); ?>"
					data-volume="<?php echo esc_attr( $item['volume_name'] ?? '' ); ?>">
					<div class="czsa-item__read">
						<a class="czsa-item__link" href="<?php echo esc_url( $item['link'] ); ?>">
							<?php if ( $item['author'] ) : ?>
							<span class="czsa-item__author"><?php echo esc_html( $item['author'] ); ?></span>
							<?php endif; ?>
							<span class="czsa-item__title"><?php echo esc_html( $item['title'] ); ?></span>
							<?php if ( $item['volume_name'] ) : ?>
							<span class="czsa-item__volume"><?php echo esc_html( $item['volume_name'] ); ?></span>
							<?php endif; ?>
							<?php if ( $item['saved_at'] ) : ?>
							<span class="czsa-item__date"><span class="czsa-item__date-label"><?php esc_html_e( 'Data di aggiunta:', 'cz-saved-articles' ); ?></span> <?php echo esc_html( $item['saved_at'] ); ?></span>
							<?php endif; ?>
						</a>
						<button type="button" class="czsa-item__remove" data-czsa-remove="<?php echo esc_attr( $item['post_id'] ); ?>" aria-label="<?php esc_attr_e( 'Rimuovi dai salvati', 'cz-saved-articles' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
						</button>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_saved_records( int $user_id ): array {
		$records = get_user_meta( $user_id, 'czsa_saved_articles', true );
		return is_array( $records ) ? $records : [];
	}

	private function is_post_saved( int $user_id, int $post_id ): bool {
		foreach ( $this->get_saved_records( $user_id ) as $r ) {
			if ( (int) ( $r['post_id'] ?? 0 ) === $post_id ) {
				return true;
			}
		}
		return false;
	}

	private function get_volume_name_for_post( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'cz_volume_items';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return null;
		}

		$volume_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT volume_id FROM {$table} WHERE post_id = %d LIMIT 1", (int) $post_id )
		);

		if ( ! $volume_id ) {
			return null;
		}

		$volume = get_post( (int) $volume_id );
		return $volume ? $volume->post_title : null;
	}
}

CZ_Saved_Articles::instance();
