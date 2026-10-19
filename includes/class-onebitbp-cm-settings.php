<?php
/**
 * Ajustes del plugin (WooCommerce → Menú de categorías).
 *
 * Todo se guarda en una sola opción (onebitbp_cm_settings). No se crean
 * tablas propias.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OneBitBP_CM_Settings {

	const OPTION = 'onebitbp_cm_settings';
	const PAGE   = 'onebitbp-cm-settings';

	public static function defaults() {
		return array(
			'enabled'         => 'yes',
			'columns'         => 6,     // Columnas por fila en escritorio.
			'depth'           => 3,     // Niveles: 1 a 4, o 0 = todos los niveles.
		);
	}

	public static function get( $key = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $opts;
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_onebitbp_cm_regenerate', array( __CLASS__, 'handle_regenerate' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			'Menú de categorías',
			'Menú de categorías',
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting( 'onebitbp_cm_settings_group', self::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		$out['enabled'] = ( isset( $in['enabled'] ) && 'yes' === $in['enabled'] ) ? 'yes' : 'no';
		$out['columns'] = isset( $in['columns'] ) ? min( 8, max( 2, absint( $in['columns'] ) ) ) : $d['columns'];
		$out['depth']   = isset( $in['depth'] ) ? self::sanitize_depth( $in['depth'] ) : $d['depth'];

		// Cualquier cambio de ajustes invalida la caché del menú.
		OneBitBP_CM_Cache::flush();

		return $out;
	}

	/**
	 * Profundidad válida: 1 a 4 niveles, o 0 = todos los niveles.
	 */
	public static function sanitize_depth( $depth ) {
		$depth = absint( $depth );
		return ( $depth >= 1 && $depth <= 4 ) ? $depth : 0;
	}

	/**
	 * Botón "Regenerar menú": invalida la caché y la vuelve a armar.
	 */
	public static function handle_regenerate() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No tenés permisos para hacer esto.' );
		}
		check_admin_referer( 'onebitbp_cm_regenerate' );

		OneBitBP_CM_Cache::flush();
		OneBitBP_CM_Tree::get_html(); // Precalienta la caché del idioma actual.

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'onebitbp_cm_done' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o        = self::get();
		$name     = self::OPTION;
		$selected = OneBitBP_CM_Tree::get_selected_summary();
		$built    = OneBitBP_CM_Cache::get_built_time();
		?>
		<div class="wrap">
			<h1>Menú de categorías <span style="font-size:13px;font-weight:400;color:#646970;">1Bit - BP Category Menu v<?php echo esc_html( ONEBITBP_CM_VERSION ); ?></span></h1>

			<?php if ( isset( $_GET['onebitbp_cm_done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p>Menú regenerado.</p></div>
			<?php endif; ?>

			<h2>Cómo se usa</h2>
			<ol>
				<li>En <strong>Productos → Categorías</strong>, en cada categoría que tenga que aparecer como columna, marcar <strong>"Incluir en menú de categorías"</strong> y, si hace falta, cargar un número de <strong>Orden</strong> (menor = más a la izquierda). Se muestra el árbol completo de esa categoría (hasta la profundidad elegida abajo).</li>
			</ol>

			<h2>Categorías incluidas</h2>
			<?php if ( empty( $selected ) ) : ?>
				<p><em>Todavía no hay ninguna categoría marcada como "Incluir en menú".</em></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:820px">
					<thead><tr><th>Orden</th><th>Categoría</th><th>Ruta</th><th>Productos</th><th>Estado</th></tr></thead>
					<tbody>
					<?php foreach ( $selected as $row ) : ?>
						<tr>
							<td><?php echo '' === $row['order'] ? '—' : (int) $row['order']; ?></td>
							<td><a href="<?php echo esc_url( get_edit_term_link( $row['id'], 'product_cat', 'product' ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
							<td><?php echo esc_html( $row['path'] ); ?></td>
							<td><?php echo (int) $row['total']; ?></td>
							<td><?php echo $row['total'] > 0 ? 'Visible' : '<span style="color:#b32d2e">Oculta (sin productos)</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p>
				<?php if ( $built ) : ?>
					Última vez armado (idioma actual): <strong><?php echo esc_html( wp_date( 'd/m/Y H:i', $built ) ); ?></strong>.
				<?php else : ?>
					El menú todavía no está armado para el idioma actual: se arma en la próxima visita.
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px">
				<input type="hidden" name="action" value="onebitbp_cm_regenerate">
				<?php wp_nonce_field( 'onebitbp_cm_regenerate' ); ?>
				<?php submit_button( 'Regenerar menú', 'secondary', 'submit', false ); ?>
			</form>

			<h2>Ajustes</h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'onebitbp_cm_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Mostrar el menú</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="yes" <?php checked( 'yes', $o['enabled'] ); ?>> Activado (si se desactiva, el shortcode no muestra nada)</label></td>
					</tr>
					<tr>
						<th scope="row">Columnas por fila (escritorio)</th>
						<td>
							<input type="number" min="2" max="8" step="1" name="<?php echo esc_attr( $name ); ?>[columns]" value="<?php echo (int) $o['columns']; ?>" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row">Profundidad</th>
						<td>
							<select name="<?php echo esc_attr( $name ); ?>[depth]">
								<option value="1" <?php selected( 1, (int) $o['depth'] ); ?>>1 nivel: solo la categoría marcada</option>
								<option value="2" <?php selected( 2, (int) $o['depth'] ); ?>>2 niveles: + sus subcategorías</option>
								<option value="3" <?php selected( 3, (int) $o['depth'] ); ?>>3 niveles: + sub-subcategorías</option>
								<option value="4" <?php selected( 4, (int) $o['depth'] ); ?>>4 niveles: + un nivel más</option>
								<option value="0" <?php selected( 0, (int) $o['depth'] ); ?>>Todos los niveles</option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
