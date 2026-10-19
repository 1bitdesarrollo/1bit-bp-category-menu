/**
 * 1Bit - BP Category Menu · flechas del megamenú (escritorio) y menú móvil.
 *
 * Escritorio: las flechas de las subcategorías despliegan u ocultan sus
 * sub-subcategorías (ver más abajo).
 *
 * Móvil:
 * WoodMart no muestra los Bloques HTML en el menú lateral móvil. Este
 * script toma el árbol que el plugin imprime oculto al final de la página
 * (#bp-cm-mobile-src) y lo coloca como submenú del ítem correspondiente,
 * con el mismo marcado que "Marcas" (ul.wd-sub-menu + span.wd-nav-opener).
 * El despliegue con un toque lo hace el propio script de WoodMart, que
 * escucha los clicks en la flecha de cualquier ítem con hijos.
 *
 * El ítem se busca así:
 *  - El ítem del menú de escritorio cuyo desplegable contiene el shortcode.
 *  - O un ítem con la clase CSS "bp-category-menu" (por si el desplegable
 *    se carga con AJAX y el shortcode no está en la página al cargar).
 */
( function () {
	'use strict';

	function itemIdOf( li ) {
		var m = li && li.className ? li.className.match( /(?:^|\s)menu-item-(\d+)(?:\s|$)/ ) : null;
		return m ? m[ 1 ] : null;
	}

	function wantedIds() {
		var ids = [];
		var menus = document.querySelectorAll( '.bp-cm' );
		for ( var i = 0; i < menus.length; i++ ) {
			var li = menus[ i ].closest( 'li.menu-item' );
			var id = itemIdOf( li );
			if ( id && ids.indexOf( id ) === -1 ) {
				ids.push( id );
			}
		}
		return ids;
	}

	function targets() {
		var ids = wantedIds();
		var out = [];
		var navs = document.querySelectorAll( '.wd-nav-mobile' );
		for ( var n = 0; n < navs.length; n++ ) {
			var lis = navs[ n ].children;
			for ( var i = 0; i < lis.length; i++ ) {
				var li = lis[ i ];
				if ( li.tagName !== 'LI' ) {
					continue;
				}
				if ( li.classList.contains( 'bp-category-menu' ) || ids.indexOf( itemIdOf( li ) ) !== -1 ) {
					out.push( li );
				}
			}
		}
		return out;
	}

	function inject( li, ul ) {
		// Ya inyectado (por ejemplo si el script corre dos veces).
		for ( var i = 0; i < li.children.length; i++ ) {
			if ( li.children[ i ].classList.contains( 'bp-cm-mobile' ) ) {
				return;
			}
		}
		var opener = null;
		for ( var j = 0; j < li.children.length; j++ ) {
			if ( li.children[ j ].classList.contains( 'wd-nav-opener' ) ) {
				opener = li.children[ j ];
			}
		}
		if ( ! opener ) {
			opener = document.createElement( 'span' );
			opener.className = 'wd-nav-opener';
			li.appendChild( opener );
		}
		// WoodMart oculta la flecha de los ítems con Bloque HTML en el menú
		// móvil (.menu-item-has-block): se quita esa clase solo en esta copia
		// del ítem para que la flecha se vea y despliegue como en "Marcas".
		li.classList.remove( 'menu-item-has-block' );
		li.classList.add( 'menu-item-has-children', 'bp-cm-mobile-parent' );
		li.insertBefore( ul, opener );
	}

	function run() {
		var src = document.getElementById( 'bp-cm-mobile-src' );
		if ( ! src ) {
			return;
		}
		var ul = src.querySelector( 'ul.bp-cm-mobile' );
		var list = targets();
		if ( ul && list.length ) {
			for ( var i = 0; i < list.length; i++ ) {
				inject( list[ i ], i === 0 ? ul : ul.cloneNode( true ) );
			}
		}
		if ( src.parentNode ) {
			src.parentNode.removeChild( src );
		}
	}

	/* ------------------------------------------------------------------
	 * Escritorio: flechas para desplegar / ocultar sub-subcategorías.
	 * Las listas arrancan ocultas por CSS; el click se escucha a nivel
	 * documento, así funciona también si el desplegable llega por AJAX.
	 * ------------------------------------------------------------------ */

	function toggleItem( li ) {
		var open = li.classList.toggle( 'is-open' );
		var btn = li.querySelector( '.bp-cm__toggle' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			var label = btn.getAttribute( 'aria-label' ) || '';
			btn.setAttribute( 'aria-label', open ? label.replace( /^Mostrar/, 'Ocultar' ) : label.replace( /^Ocultar/, 'Mostrar' ) );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var target = e.target;
		if ( ! target || ! target.closest ) {
			return;
		}
		var nav = target.closest( '.bp-cm--collapsible' );
		if ( ! nav ) {
			return;
		}
		var btn = target.closest( '.bp-cm__toggle' );
		if ( ! btn ) {
			return; // El nombre de la subcategoría sigue siendo un enlace normal.
		}
		e.preventDefault();
		e.stopPropagation();
		toggleItem( btn.closest( '.bp-cm__item' ) );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
