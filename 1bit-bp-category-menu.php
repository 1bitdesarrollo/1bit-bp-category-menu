<?php
/**
 * Plugin Name:       1Bit - BP Category Menu
 * Plugin URI:        https://blackpaprikastore.com.ar
 * Description:       Megamenú con los árboles de las categorías de WooCommerce marcadas como "Incluir en menú de categorías". Se inserta con el shortcode [bp_category_menu] en un Bloque HTML de WoodMart asignado a un ítem del menú, y en móvil se muestra como submenú desplegable.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            1Bit
 * Author URI:        https://blackpaprikastore.com.ar
 * Text Domain:       1bit-bp-category-menu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Salir si se accede directamente al archivo.
}

// Evita cargar el plugin dos veces (por ejemplo si en el futuro se suma
// como módulo a la suite y esta copia sigue activa).
if ( defined( 'ONEBITBP_CM_VERSION' ) ) {
	return;
}

define( 'ONEBITBP_CM_VERSION', '1.0.0' );
define( 'ONEBITBP_CM_FILE', __FILE__ );
define( 'ONEBITBP_CM_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONEBITBP_CM_URL', plugin_dir_url( __FILE__ ) );

require_once ONEBITBP_CM_DIR . 'includes/class-onebitbp-cm-settings.php';
require_once ONEBITBP_CM_DIR . 'includes/class-onebitbp-cm-cache.php';
require_once ONEBITBP_CM_DIR . 'includes/class-onebitbp-cm-tree.php';
require_once ONEBITBP_CM_DIR . 'includes/class-onebitbp-cm-term-fields.php';
require_once ONEBITBP_CM_DIR . 'includes/class-onebitbp-cm-frontend.php';

/**
 * Arranque: solo si WooCommerce está activo (la taxonomía product_cat es suya).
 */
function onebitbp_cm_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'onebitbp_cm_notice_no_wc' );
		return;
	}

	OneBitBP_CM_Settings::init();
	OneBitBP_CM_Cache::init();
	OneBitBP_CM_Term_Fields::init();
	OneBitBP_CM_Frontend::init();
}
add_action( 'plugins_loaded', 'onebitbp_cm_init', 20 );

function onebitbp_cm_notice_no_wc() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>1Bit - BP Category Menu</strong> necesita WooCommerce activo. El plugin queda en pausa hasta que se active.</p></div>';
}

/**
 * Al activar o desactivar, se invalida la caché del menú.
 */
function onebitbp_cm_flush_on_toggle() {
	update_option( 'onebitbp_cm_cache_ver', (string) microtime( true ), true );
}
register_activation_hook( __FILE__, 'onebitbp_cm_flush_on_toggle' );
register_deactivation_hook( __FILE__, 'onebitbp_cm_flush_on_toggle' );

/**
 * Enlace "Ajustes" en el listado de plugins.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=onebitbp-cm-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Ajustes</a>' );
		return $links;
	}
);
