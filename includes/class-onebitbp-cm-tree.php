<?php
/**
 * Armado del árbol de categorías y de su HTML (escritorio y móvil).
 *
 * Una sola consulta trae todas las categorías de producto; el árbol se
 * arma en memoria y el HTML resultante se guarda en caché
 * (OneBitBP_CM_Cache). Mientras la caché esté vigente no se vuelve a
 * consultar nada.
 *
 * @package OneBitBP_Category_Menu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OneBitBP_CM_Tree {

	const META_INCLUDE = 'onebitbp_cm_include';
	const META_ORDER   = 'onebitbp_cm_order';

	/** @var array|null Índices de la petición actual (se arman una vez). */
	private static $index = null;

	/* ------------------------------------------------------------------
	 * Datos
	 * ------------------------------------------------------------------ */

	/**
	 * Trae todas las categorías y arma los índices por id y por padre,
	 * con el total de productos de cada una contando sus descendientes.
	 */
	private static function index() {
		if ( null !== self::$index ) {
			return self::$index;
		}

		$terms = get_terms(
			array(
				'taxonomy'               => 'product_cat',
				'hide_empty'             => false,
				'update_term_meta_cache' => true, // Trae todos los meta en una sola consulta.
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$by_id     = array();
		$by_parent = array();
		foreach ( $terms as $t ) {
			$by_id[ (int) $t->term_id ]           = $t;
			$by_parent[ (int) $t->parent ][]      = (int) $t->term_id;
		}

		self::$index = array(
			'by_id'     => $by_id,
			'by_parent' => $by_parent,
			'totals'    => array(),
		);

		// Total = productos propios + los de todas las subcategorías.
		foreach ( array_keys( $by_id ) as $id ) {
			self::total( $id );
		}

		return self::$index;
	}

	private static function total( $id, $guard = 0 ) {
		if ( isset( self::$index['totals'][ $id ] ) ) {
			return self::$index['totals'][ $id ];
		}
		if ( $guard > 20 ) { // Protección ante jerarquías corruptas.
			return 0;
		}
		$sum = isset( self::$index['by_id'][ $id ] ) ? (int) self::$index['by_id'][ $id ]->count : 0;
		if ( ! empty( self::$index['by_parent'][ $id ] ) ) {
			foreach ( self::$index['by_parent'][ $id ] as $child ) {
				$sum += self::total( $child, $guard + 1 );
			}
		}
		self::$index['totals'][ $id ] = $sum;
		return $sum;
	}

	/**
	 * Orden de un término: número cargado en "Orden" o null si está vacío.
	 */
	private static function order_of( $id ) {
		$v = get_term_meta( $id, self::META_ORDER, true );
		return ( '' === $v || null === $v || false === $v ) ? null : (int) $v;
	}

	/**
	 * Ordena ids: primero los que tienen "Orden" (de menor a mayor), después
	 * el resto alfabéticamente (sin tener en cuenta acentos ni mayúsculas).
	 */
	private static function sort_ids( array $ids ) {
		$by_id = self::$index['by_id'];
		usort(
			$ids,
			function ( $a, $b ) use ( $by_id ) {
				$oa = self::order_of( $a );
				$ob = self::order_of( $b );
				if ( null !== $oa && null !== $ob && $oa !== $ob ) {
					return $oa <=> $ob;
				}
				if ( null !== $oa && null === $ob ) {
					return -1;
				}
				if ( null === $oa && null !== $ob ) {
					return 1;
				}
				$na = strtolower( remove_accents( wp_specialchars_decode( $by_id[ $a ]->name ) ) );
				$nb = strtolower( remove_accents( wp_specialchars_decode( $by_id[ $b ]->name ) ) );
				return strnatcmp( $na, $nb );
			}
		);
		return $ids;
	}

	/**
	 * Ids de las categorías marcadas como "Incluir en menú", ya ordenadas.
	 */
	public static function selected_ids( $only_visible = true ) {
		$idx = self::index();
		$ids = array();
		foreach ( $idx['by_id'] as $id => $t ) {
			if ( '1' !== (string) get_term_meta( $id, self::META_INCLUDE, true ) ) {
				continue;
			}
			if ( $only_visible && self::$index['totals'][ $id ] < 1 ) {
				continue;
			}
			$ids[] = $id;
		}
		return self::sort_ids( $ids );
	}

	/**
	 * Árbol de un término hasta $depth niveles, omitiendo subcategorías vacías.
	 *
	 * @return array{ id:int, name:string, url:string, children:array }
	 */
	private static function node( $id, $depth, $level = 1 ) {
		$t    = self::$index['by_id'][ $id ];
		$link = get_term_link( $t );
		$node = array(
			'id'       => (int) $id,
			'name'     => $t->name,
			'url'      => is_wp_error( $link ) ? '' : $link,
			'children' => array(),
		);
		if ( $level < $depth && ! empty( self::$index['by_parent'][ $id ] ) ) {
			$children = array();
			foreach ( self::$index['by_parent'][ $id ] as $child ) {
				if ( self::$index['totals'][ $child ] > 0 ) {
					$children[] = $child;
				}
			}
			foreach ( self::sort_ids( $children ) as $child ) {
				$node['children'][] = self::node( $child, $depth, $level + 1 );
			}
		}
		return $node;
	}

	/**
	 * Árbol completo del menú: una entrada por categoría marcada.
	 */
	public static function get_tree( $depth ) {
		$depth = OneBitBP_CM_Settings::sanitize_depth( $depth );
		$limit = $depth ? $depth : 20; // 0 = todos los niveles (20 = tope de seguridad ante jerarquías corruptas).
		$tree  = array();
		foreach ( self::selected_ids() as $id ) {
			$tree[] = self::node( $id, $limit );
		}
		/**
		 * Permite ajustar el árbol antes de convertirlo en HTML.
		 *
		 * @param array $tree  Árbol (id, name, url, children).
		 * @param int   $depth Profundidad.
		 */
		return apply_filters( 'onebitbp_cm_tree', $tree, $depth );
	}

	/**
	 * Resumen para la página de ajustes (incluye las marcadas que hoy están
	 * ocultas por no tener productos).
	 */
	public static function get_selected_summary() {
		$idx  = self::index();
		$rows = array();
		foreach ( self::selected_ids( false ) as $id ) {
			$t     = $idx['by_id'][ $id ];
			$path  = array();
			$p     = (int) $t->parent;
			$guard = 0;
			while ( $p && isset( $idx['by_id'][ $p ] ) && $guard++ < 20 ) {
				array_unshift( $path, wp_specialchars_decode( $idx['by_id'][ $p ]->name ) );
				$p = (int) $idx['by_id'][ $p ]->parent;
			}
			$order  = self::order_of( $id );
			$rows[] = array(
				'id'    => $id,
				'name'  => wp_specialchars_decode( $t->name ),
				'path'  => $path ? implode( ' › ', $path ) . ' ›' : '(raíz)',
				'order' => null === $order ? '' : $order,
				'total' => (int) self::$index['totals'][ $id ],
			);
		}
		return $rows;
	}

	/* ------------------------------------------------------------------
	 * HTML
	 * ------------------------------------------------------------------ */

	/**
	 * Clave de variante para la caché.
	 */
	public static function variant( $type, $columns = null, $depth = null ) {
		$s       = OneBitBP_CM_Settings::get();
		$columns = null === $columns ? (int) $s['columns'] : (int) $columns;
		$depth   = null === $depth ? (int) $s['depth'] : (int) $depth;
		if ( 'mobile' === $type ) {
			return 'mobile|d' . (int) $s['depth'];
		}
		return 'desktop|c' . $columns . '|d' . $depth;
	}

	/**
	 * HTML del megamenú de escritorio (desde caché o armado en el momento).
	 */
	public static function get_html( $columns = null, $depth = null ) {
		$s       = OneBitBP_CM_Settings::get();
		$columns = null === $columns ? (int) $s['columns'] : min( 8, max( 2, (int) $columns ) );
		$depth   = OneBitBP_CM_Settings::sanitize_depth( null === $depth ? $s['depth'] : $depth );
		$variant = self::variant( 'desktop', $columns, $depth );

		$cached = OneBitBP_CM_Cache::get( $variant );
		if ( false !== $cached ) {
			return $cached['html'];
		}

		$html = self::render_desktop( self::get_tree( $depth ), $columns );
		$html = apply_filters( 'onebitbp_cm_html', $html, 'desktop' );
		OneBitBP_CM_Cache::set( $variant, $html );
		return $html;
	}

	/**
	 * HTML del submenú móvil (desde caché o armado en el momento).
	 */
	public static function get_mobile_html() {
		$variant = self::variant( 'mobile' );
		$cached  = OneBitBP_CM_Cache::get( $variant );
		if ( false !== $cached ) {
			return $cached['html'];
		}

		$s    = OneBitBP_CM_Settings::get();
		$html = self::render_mobile( self::get_tree( (int) $s['depth'] ) );
		$html = apply_filters( 'onebitbp_cm_html', $html, 'mobile' );
		OneBitBP_CM_Cache::set( $variant, $html );
		return $html;
	}

	private static function link( $node, $class ) {
		return '<a href="' . esc_url( $node['url'] ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $node['name'] ) . '</a>';
	}

	/**
	 * Grilla de columnas: una por categoría marcada (título) con su árbol.
	 */
	private static function render_desktop( array $tree, $columns ) {
		if ( empty( $tree ) ) {
			return '';
		}
		$h  = '<nav class="bp-cm bp-cm--collapsible" aria-label="Categorías" style="--bp-cm-cols:' . (int) $columns . '">';
		$h .= '<div class="bp-cm__grid">';
		foreach ( $tree as $root ) {
			$h .= '<div class="bp-cm__col">';
			$h .= self::link( $root, 'bp-cm__title' );
			if ( ! empty( $root['children'] ) ) {
				$h .= self::render_desktop_list( $root['children'], 2 );
			}
			$h .= '</div>';
		}
		$h .= '</div></nav>';
		return $h;
	}

	/**
	 * Lista de un nivel (2 = subcategorías de la columna, 3 en adelante =
	 * sub-subcategorías). Cada ítem con hijas lleva una flecha que despliega
	 * u oculta el nivel siguiente; sirve para cualquier profundidad.
	 */
	private static function render_desktop_list( array $nodes, $level ) {
		$h = '<ul class="' . ( 2 === $level ? 'bp-cm__list' : 'bp-cm__sublist' ) . ' bp-cm__level-' . (int) $level . '">';
		foreach ( $nodes as $n ) {
			$has  = ! empty( $n['children'] );
			$link = self::link( $n, 'bp-cm__link bp-cm__link--l' . min( 3, (int) $level ) );
			$h   .= '<li class="bp-cm__item' . ( $has ? ' bp-cm__item--has-children' : '' ) . '">';
			if ( $has ) {
				$h .= '<div class="bp-cm__row">' . $link;
				$h .= '<button type="button" class="bp-cm__toggle" aria-expanded="false" aria-label="' . esc_attr( 'Mostrar subcategorías de ' . wp_specialchars_decode( $n['name'] ) ) . '"><span class="bp-cm__chevron" aria-hidden="true"></span></button>';
				$h .= '</div>';
				$h .= self::render_desktop_list( $n['children'], $level + 1 );
			} else {
				$h .= $link;
			}
			$h .= '</li>';
		}
		$h .= '</ul>';
		return $h;
	}

	/**
	 * Submenú móvil con el mismo marcado que usa WoodMart en "Marcas"
	 * (ul.wd-sub-menu + span.wd-nav-opener), así el despliegue con un toque
	 * lo maneja el propio script del tema.
	 */
	private static function render_mobile( array $tree ) {
		if ( empty( $tree ) ) {
			return '';
		}
		return '<ul class="wd-sub-menu bp-cm-mobile">' . self::render_mobile_items( $tree, 1 ) . '</ul>';
	}

	private static function render_mobile_items( array $nodes, $level ) {
		$h = '';
		foreach ( $nodes as $n ) {
			$has = ! empty( $n['children'] );
			$h  .= '<li class="menu-item item-level-' . (int) $level . ( $has ? ' menu-item-has-children' : '' ) . '">';
			$h  .= self::link( $n, 'woodmart-nav-link' );
			if ( $has ) {
				$h .= '<ul class="wd-sub-menu">' . self::render_mobile_items( $n['children'], $level + 1 ) . '</ul>';
				$h .= '<span class="wd-nav-opener"></span>';
			}
			$h .= '</li>';
		}
		return $h;
	}
}
