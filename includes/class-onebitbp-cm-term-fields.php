<?php
/**
 * Campos "Incluir en menú" y "Orden" en el formulario de categorías de
 * producto (Productos → Categorías), más una columna en el listado.
 *
 * Se guardan como term meta (tabla wp_termmeta), igual que WooCommerce
 * guarda la miniatura y WoodMart el icono de la categoría. No se modifica
 * ninguna tabla.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OneBitBP_CM_Term_Fields {

	const NONCE = 'onebitbp_cm_term_nonce';

	public static function init() {
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'add_fields' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'edit_fields' ), 20 );

		// Prioridad 5: se guarda antes de que la caché se invalide (prioridad 10).
		add_action( 'created_product_cat', array( __CLASS__, 'save' ), 5 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save' ), 5 );

		add_filter( 'manage_edit-product_cat_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'manage_product_cat_custom_column', array( __CLASS__, 'render_column' ), 10, 3 );
	}

	/**
	 * Bloque "Menú de categorías": mismo contenido en el alta y en la edición.
	 * Va separado del resto del formulario por una línea arriba y otra abajo,
	 * sin líneas internas.
	 */
	private static function render_box( $include, $order ) {
		wp_nonce_field( 'onebitbp_cm_save_term', self::NONCE );
		?>
		<style>
			.onebitbp-cm-box{border-top:1px solid #c3c4c7;border-bottom:1px solid #c3c4c7;padding:14px 0 6px;margin:12px 0}
			.onebitbp-cm-box h2{margin:0 0 10px;padding:0;font-size:1.3em}
			.onebitbp-cm-box label{display:block;margin:0;font-weight:400}
			.onebitbp-cm-box .onebitbp-cm-label{font-weight:600;margin-bottom:4px}
			.onebitbp-cm-box input[type=checkbox]{margin:0 6px 0 0}
			.onebitbp-cm-box input[type=number]{width:100px}
			.onebitbp-cm-box p.description{margin:4px 0 14px}
		</style>
		<div class="onebitbp-cm-box">
			<h2>Menú de categorías</h2>
			<label><input type="checkbox" name="onebitbp_cm_include" value="1" <?php checked( $include ); ?>> Incluir en menú de categorías</label>
			<p class="description">Se muestra con todo su árbol de subcategorías. Las categorías sin productos no se muestran.</p>
			<label for="onebitbp_cm_order" class="onebitbp-cm-label">Orden:</label>
			<input type="number" name="onebitbp_cm_order" id="onebitbp_cm_order" value="<?php echo esc_attr( $order ); ?>" min="0" step="1">
			<p class="description">Menor número = más a la izquierda. Vacío = después de las numeradas, por orden alfabético. También ordena las subcategorías dentro de su columna.</p>
		</div>
		<?php
	}

	/**
	 * Formulario "Añadir nueva categoría".
	 */
	public static function add_fields() {
		self::render_box( false, '' );
	}

	/**
	 * Formulario "Editar categoría": una sola fila a todo el ancho de la tabla.
	 */
	public static function edit_fields( $term ) {
		$include = '1' === (string) get_term_meta( $term->term_id, OneBitBP_CM_Tree::META_INCLUDE, true );
		$order   = get_term_meta( $term->term_id, OneBitBP_CM_Tree::META_ORDER, true );
		echo '<tr class="onebitbp-cm-row"><td colspan="2" style="padding-left:0;padding-right:0">';
		self::render_box( $include, $order );
		echo '</td></tr>';
	}

	/**
	 * Guarda los campos. Solo si vienen del formulario (con nonce): la
	 * edición rápida del listado no los manda y así no se pierden.
	 */
	public static function save( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'onebitbp_cm_save_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( ! empty( $_POST['onebitbp_cm_include'] ) ) {
			update_term_meta( $term_id, OneBitBP_CM_Tree::META_INCLUDE, '1' );
		} else {
			delete_term_meta( $term_id, OneBitBP_CM_Tree::META_INCLUDE );
		}

		$order = isset( $_POST['onebitbp_cm_order'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['onebitbp_cm_order'] ) ) ) : '';
		if ( '' !== $order && is_numeric( $order ) ) {
			update_term_meta( $term_id, OneBitBP_CM_Tree::META_ORDER, max( 0, (int) $order ) );
		} else {
			delete_term_meta( $term_id, OneBitBP_CM_Tree::META_ORDER );
		}

		OneBitBP_CM_Cache::flush();
	}

	public static function add_column( $columns ) {
		$columns['onebitbp_cm'] = 'Menú';
		return $columns;
	}

	public static function render_column( $content, $column, $term_id ) {
		if ( 'onebitbp_cm' !== $column ) {
			return $content;
		}
		$include = '1' === (string) get_term_meta( $term_id, OneBitBP_CM_Tree::META_INCLUDE, true );
		$order   = get_term_meta( $term_id, OneBitBP_CM_Tree::META_ORDER, true );
		$out     = $include ? '<span title="Incluida en el menú" style="color:#00a32a;font-weight:600">✓</span>' : '<span style="color:#a7aaad">—</span>';
		if ( '' !== (string) $order ) {
			$out .= ' <span style="color:#646970">(' . (int) $order . ')</span>';
		}
		return $content . $out;
	}
}
