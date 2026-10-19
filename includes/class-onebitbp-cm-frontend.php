<?php
/**
 * Front: shortcode [bp_category_menu], árbol para el menú móvil y recursos.
 *
 * - Escritorio: el shortcode va dentro del Bloque HTML de WoodMart
 *   asignado al ítem del menú. El HTML ya viene en la página y WoodMart lo
 *   muestra al pasar el mouse: el hover no hace ninguna llamada. Las
 *   sub-subcategorías se despliegan con una flecha (JS, sin llamadas).
 * - Móvil: WoodMart no muestra los Bloques HTML en el menú lateral, así
 *   que el plugin imprime el árbol (oculto) al final de la página y un JS
 *   lo mueve como submenú del mismo ítem, con el marcado de WoodMart.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OneBitBP_CM_Frontend {

	public static function init() {
		add_shortcode( 'bp_category_menu', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'mobile_source' ), 5 );
	}

	private static function active() {
		return 'yes' === OneBitBP_CM_Settings::get( 'enabled' );
	}

	/**
	 * [bp_category_menu columns="6" depth="3"]
	 */
	public static function shortcode( $atts ) {
		if ( ! self::active() ) {
			return '';
		}
		$atts = shortcode_atts(
			array(
				'columns' => null,
				'depth'   => null,
			),
			$atts,
			'bp_category_menu'
		);

		// Si el shortcode se usa dentro de una petición AJAX (WoodMart con
		// "Cargar con AJAX" o el editor de Elementor) los estilos pueden no
		// estar encolados: se asegura que el CSS esté disponible.
		self::assets();
		wp_enqueue_style( 'onebitbp-cm' );

		$html = OneBitBP_CM_Tree::get_html(
			null === $atts['columns'] || '' === $atts['columns'] ? null : (int) $atts['columns'],
			null === $atts['depth'] || '' === $atts['depth'] ? null : (int) $atts['depth']
		);

		if ( '' === $html && current_user_can( 'manage_woocommerce' ) ) {
			return '<p class="bp-cm-empty" style="padding:15px;background:#fff;color:#000">Menú de categorías: no hay categorías marcadas como "Incluir en menú" (o no tienen productos). Este aviso solo lo ven los administradores.</p>';
		}
		return $html;
	}

	public static function assets() {
		if ( wp_style_is( 'onebitbp-cm', 'registered' ) ) {
			return;
		}
		wp_register_style( 'onebitbp-cm', ONEBITBP_CM_URL . 'assets/bp-category-menu.css', array(), ONEBITBP_CM_VERSION );
		wp_register_script( 'onebitbp-cm', ONEBITBP_CM_URL . 'assets/bp-category-menu.js', array(), ONEBITBP_CM_VERSION, true );

		if ( ! self::active() || is_admin() ) {
			return;
		}
		// El CSS pesa poco y se carga siempre para que el megamenú se vea
		// bien desde el primer hover (el bloque está en el header de todas las páginas).
		wp_enqueue_style( 'onebitbp-cm' );

		// El JS maneja las flechas del megamenú (escritorio) y el árbol del menú móvil.
		wp_enqueue_script( 'onebitbp-cm' );
	}

	/**
	 * Árbol para el menú móvil, oculto al final de la página. Se usa un div
	 * con "hidden" (y no <template>) para que TranslatePress lo traduzca.
	 */
	public static function mobile_source() {
		if ( ! self::active() || is_admin() ) {
			return;
		}
		$html = OneBitBP_CM_Tree::get_mobile_html();
		if ( '' === $html ) {
			return;
		}
		echo '<div id="bp-cm-mobile-src" hidden aria-hidden="true" style="display:none">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML armado y escapado en OneBitBP_CM_Tree.
	}
}
