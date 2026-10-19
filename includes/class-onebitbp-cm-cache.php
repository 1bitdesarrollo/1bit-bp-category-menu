<?php
/**
 * Caché del menú armado.
 *
 * El HTML del menú se arma una sola vez y se guarda en un transient (uno
 * por idioma y por variante). Las cargas siguientes solo leen ese registro.
 *
 * Para invalidar no se borran los transients uno por uno: se cambia un
 * número de "versión" (opción onebitbp_cm_cache_ver) que forma parte de la
 * clave. Así funciona igual con o sin object cache (Redis / Memcached) y
 * para todos los idiomas a la vez. Los registros viejos vencen solos.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OneBitBP_CM_Cache {

	const VER_OPTION = 'onebitbp_cm_cache_ver';
	const PREFIX     = 'onebitbp_cm_';
	const TTL        = 12 * HOUR_IN_SECONDS; // Resguardo: se rearma solo cada 12 h.

	/** @var bool Evita invalidar varias veces en la misma petición (importaciones masivas). */
	private static $flushed = false;

	public static function init() {
		// Cambios en las categorías (alta, edición, borrado; incluye nuestros campos).
		add_action( 'created_product_cat', array( __CLASS__, 'flush' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'flush' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'flush' ) );

		// Cambios en qué productos tiene cada categoría (afecta "omitir vacías").
		add_action( 'set_object_terms', array( __CLASS__, 'on_set_object_terms' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 2 );

		// Recuento de términos de WooCommerce (por ejemplo tras cambios de stock / visibilidad).
		add_action( 'woocommerce_product_set_visibility', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Invalida toda la caché del menú (todos los idiomas y variantes).
	 */
	public static function flush() {
		if ( self::$flushed ) {
			return;
		}
		self::$flushed = true;
		update_option( self::VER_OPTION, (string) microtime( true ), true );
	}

	public static function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'product_cat' === $taxonomy ) {
			self::flush();
		}
	}

	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( ! $post || 'product' !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::flush();
		}
	}

	public static function on_deleted_post( $post_id, $post = null ) {
		if ( $post && 'product' === $post->post_type ) {
			self::flush();
		}
	}

	/**
	 * Idioma actual (TranslatePress cambia el locale en /en/), para no
	 * mezclar URLs de un idioma con otro.
	 */
	public static function locale() {
		return function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	}

	public static function key( $variant ) {
		$ver = (string) get_option( self::VER_OPTION, '0' );
		return self::PREFIX . md5( ONEBITBP_CM_VERSION . '|' . $ver . '|' . self::locale() . '|' . $variant );
	}

	public static function get( $variant ) {
		$data = get_transient( self::key( $variant ) );
		return ( is_array( $data ) && isset( $data['html'] ) ) ? $data : false;
	}

	public static function set( $variant, $html ) {
		set_transient(
			self::key( $variant ),
			array(
				'html'  => $html,
				'built' => time(),
			),
			self::TTL
		);
	}

	/**
	 * Hora en que se armó la variante por defecto (para la página de ajustes).
	 */
	public static function get_built_time() {
		$data = self::get( OneBitBP_CM_Tree::variant( 'desktop' ) );
		return $data ? (int) $data['built'] : 0;
	}
}
