<?php
/**
 * Limpieza al borrar el plugin desde wp-admin → Plugins → Borrar.
 *
 * Borra los ajustes, la caché y los campos "Incluir en menú" / "Orden" de
 * las categorías. Desactivar el plugin NO borra nada.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'onebitbp_cm_settings' );
delete_option( 'onebitbp_cm_cache_ver' );

delete_metadata( 'term', 0, 'onebitbp_cm_include', '', true );
delete_metadata( 'term', 0, 'onebitbp_cm_order', '', true );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_onebitbp\\_cm\\_%' OR option_name LIKE '\\_transient\\_timeout\\_onebitbp\\_cm\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
