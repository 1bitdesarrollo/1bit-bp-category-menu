# 1Bit - BP Category Menu

Plugin de WordPress a medida para [Black Paprika Store](https://blackpaprikastore.com.ar), desarrollado por **1Bit**.

Arma un **megamenú con los árboles de categorías** de WooCommerce, una columna por cada categoría marcada como **"Incluir en menú"**, con su árbol de subcategorías. Se inserta con un shortcode en un Bloque HTML de WoodMart asignado a un ítem del menú (por ejemplo "Productos"). En móvil se muestra como submenú desplegable con un toque, igual que "Marcas".

Plugin independiente (no forma parte de 1Bit - BP Plugin Suite).

| | |
|---|---|
| **Versión** | 1.0.1 |
| **Requiere WordPress** | 5.8 o superior |
| **Requiere PHP** | 7.4 o superior |
| **Licencia** | [GPLv2 o posterior](https://www.gnu.org/licenses/gpl-2.0.html) |

## Requisitos

- WordPress + [WooCommerce](https://woocommerce.com/) (si WooCommerce no está activo, el plugin queda en pausa y avisa en el escritorio).
- Tema **WoodMart** (megamenú con Bloque HTML y menú lateral móvil).
- **TranslatePress** (opcional): los nombres y las URL se traducen solos en `/en/`.

## Instalación

1. wp-admin → Plugins → Añadir nuevo → Subir plugin → `1bit-bp-category-menu.zip` → Activar.
2. **Productos → Categorías**: en cada categoría que deba ser una columna, marcar **"Incluir en menú de categorías"** y, si hace falta, un número de **Orden**.
3. **WoodMart → Bloques HTML**: en el bloque del megamenú, agregar un widget **Shortcode** con `[bp_category_menu]` (y quitar el resto del contenido de prueba).
4. **Apariencia → Menús**, en el ítem "Productos":
   - *Bloque HTML para el desplegable*: el bloque del paso 3.
   - *Diseño*: **Establecer tamaños** con *Ancho del desplegable* en px (por ejemplo 1600): el panel arranca debajo del ítem; en pantallas más chicas el plugin lo achica al ancho de la pantalla y WoodMart lo corre a la izquierda para que no se salga del sitio.
   - *Esquema de color del desplegable*: indistinto (el plugin fuerza fondo blanco y sus propios colores).
   - *Cargar desplegable HTML con AJAX*: **No** (así el menú ya viene en la página y el hover no hace ninguna llamada).
   - *URL*: conviene poner `/tienda/` en vez de `#`.
5. Ajustes opcionales en **WooCommerce → Menú de categorías**.

## Cómo funciona

### Datos

- Dos campos en el formulario de cada categoría de producto (alta y edición), guardados como **term meta** (`wp_termmeta`), igual que WooCommerce guarda la miniatura y WoodMart el icono. **No se crea ni se modifica ninguna tabla.**
  - `onebitbp_cm_include` = `1` → "Incluir en menú de categorías".
  - `onebitbp_cm_order` → "Orden" (número; menor = más a la izquierda).
- El listado de categorías suma una columna **"Menú"** con ✓ y el orden.
- La edición rápida del listado no toca estos campos.

### Árbol

- Cada categoría marcada es una columna; se muestra su árbol hasta la **profundidad** elegida (por defecto 3 niveles: categoría → subcategoría → sub-subcategoría; se puede elegir 1 a 4 niveles o todos).
- **Orden:** primero las que tienen número de Orden (de menor a mayor) y después el resto en orden alfabético. Aplica a las columnas y a las subcategorías dentro de cada columna.
- **Categorías vacías:** se omiten las que no tienen productos ni en ellas ni en ninguna subcategoría.
- Si la misma rama existe en varios lugares (por ejemplo "Tecnología" como raíz y dentro de "Stamina"), se muestra cada una que esté marcada: conviene marcar una sola.

### Sub-subcategorías desplegables (escritorio)

Las subcategorías que tienen hijas muestran una **flecha**: las sub-subcategorías arrancan ocultas y se despliegan u ocultan con un click, para que el panel no crezca tanto en alto. Ejemplo: *Bolsas & Viajes* → *Accesorios de viaje* ⌄ → (Accesorios, Monederos y Riñoneras, Organizadores y Neceseres).

- Clic en el **nombre** de la subcategoría: abre esa categoría. Solo la flecha despliega u oculta.
- Cada subcategoría se abre y cierra por separado; no hace llamadas al servidor.

### Caché (sin llamadas en cada hover)

1. La primera vez se hace **una sola consulta** con todas las categorías, se arma el árbol y el HTML final, y se guarda en un *transient* (uno por idioma).
2. Las cargas siguientes solo leen ese HTML guardado.
3. El hover **no hace ninguna llamada**: el HTML ya está en la página y WoodMart lo muestra con CSS.

Se vuelve a armar **solo cuando cambia algo**:

- alta, edición o borrado de una categoría (nombre, padre, slug, "Incluir en menú", "Orden");
- productos que entran o salen de una categoría, o que se publican, despublican o borran (afecta "omitir vacías");
- cambios en los ajustes del plugin, o al activarlo o desactivarlo;
- botón **"Regenerar menú"** en WooCommerce → Menú de categorías;
- como resguardo, cada 12 horas (cubre importaciones por CSV/SQL que no disparan los hooks de WordPress).

Para invalidar se cambia un número de versión que forma parte de la clave de la caché (`onebitbp_cm_cache_ver`), así se invalidan todos los idiomas a la vez y funciona igual con object cache (Redis / Memcached).

> ⚠️ Con *Diseño = Predeterminado* el desplegable de WoodMart mide unos 180 px de ancho y la grilla no entra: hay que usar **Ancho completo**.

> ⚠️ Si se activa una caché de página completa (Cloudflare "Cache Everything", un plugin de caché o la del hosting), después de cambiar categorías hay que purgarla para ver el menú nuevo.

### Móvil

WoodMart **no muestra Bloques HTML en el menú lateral móvil** (el ítem aparece con la flecha pero sin contenido). Por eso el plugin imprime el árbol oculto al final de la página y un script lo coloca como submenú del ítem, con el mismo marcado que usa WoodMart en "Marcas" (`ul.wd-sub-menu` + `span.wd-nav-opener`). El despliegue con un toque en la flecha lo hace el propio script del tema, en todos los niveles.

- El ítem se detecta solo (el que tiene el shortcode en su desplegable de escritorio). Si se activa "Cargar con AJAX" en WoodMart, hay que agregarle al ítem la clase CSS `bp-category-menu` (Apariencia → Menús → Clases CSS).
- El menú móvil no tiene ajustes: sigue siempre la misma profundidad y categorías que el de escritorio.

### Ajustes (WooCommerce → Menú de categorías)

Guardados en la opción `onebitbp_cm_settings`:

- Activar / desactivar el menú.
- Columnas por fila en escritorio (2 a 8; por defecto 6). Debajo de 1300 px de ancho las columnas se acomodan solas.
- Profundidad: 1, 2, 3 o 4 niveles, o "Todos los niveles".
- Listado de las categorías incluidas con su orden, ruta y cantidad de productos, y si hoy están visibles u ocultas por estar vacías.
- Botón "Regenerar menú".

## Shortcode

```
[bp_category_menu]
[bp_category_menu columns="5" depth="2"]
```

- `columns`: columnas por fila (2 a 8). Por defecto, el valor de los ajustes.
- `depth`: niveles (1 a 4, o 0 = todos los niveles). Por defecto, el valor de los ajustes.

Si no hay categorías marcadas, los administradores ven un aviso en lugar del menú; los visitantes no ven nada.

## Filtros

- `onebitbp_cm_tree( $tree, $depth )`: ajustar el árbol antes de armar el HTML.
- `onebitbp_cm_html( $html, $type )`: ajustar el HTML final (`$type` = `desktop` o `mobile`).

## Desinstalación

Desactivar no borra nada. **Borrar** el plugin desde wp-admin elimina sus ajustes, la caché y los campos "Incluir en menú" / "Orden" de todas las categorías.

## Estructura del proyecto

```
1bit-bp-category-menu/
├── 1bit-bp-category-menu.php                # Cabecera y arranque (requiere WooCommerce)
├── uninstall.php                            # Limpieza al borrar el plugin
├── assets/
│   ├── bp-category-menu.css                 # Estilos del megamenú y sangría en móvil
│   └── bp-category-menu.js                  # Inserta el árbol en el menú móvil de WoodMart
└── includes/
    ├── class-onebitbp-cm-settings.php       # WooCommerce → Menú de categorías
    ├── class-onebitbp-cm-cache.php          # Caché e invalidación
    ├── class-onebitbp-cm-tree.php           # Árbol de categorías y HTML (escritorio / móvil)
    ├── class-onebitbp-cm-term-fields.php    # Campos en el formulario de categorías y columna "Menú"
    └── class-onebitbp-cm-frontend.php       # Shortcode, árbol móvil y recursos
```

## Changelog

### 1.0.1
- Colores iguales al menú principal de WoodMart ("Productos", "Marcas"): títulos de columna y textos con `--nav-color` (hoy #333333) y hover con `--nav-color-hover` (hoy el color principal del tema, #030304), tomados del tema. Si cambian en WoodMart, el megamenú los sigue.
- Subcategorías: siguen en el gris de los submenús y al pasar el mouse toman el mismo color de hover.
- Se quita el subrayado al pasar el mouse por los títulos de columna.

### 1.0.0
- Versión inicial para producción:
  - Campos "Incluir en menú de categorías" y "Orden" en el formulario de las categorías de producto, y columna "Menú" en el listado.
  - Shortcode `[bp_category_menu]` para el Bloque HTML de WoodMart: grilla de columnas con fondo blanco, títulos en el color del menú principal y subcategorías en el gris de los submenús del tema (`--sub-menu-color`).
  - Profundidad de 1 a 4 niveles o todos; cada nivel con hijas se despliega con una flecha.
  - Se omiten las categorías sin productos.
  - Caché por idioma con invalidación automática ante cambios de categorías, productos o ajustes, resguardo de 12 h y botón "Regenerar menú".
  - Ancho adaptable: con el diseño "Establecer tamaños", el panel nunca supera el ancho de la pantalla.
  - Menú móvil de WoodMart: el mismo árbol como submenú desplegable, igual que "Marcas".
  - Página WooCommerce → Menú de categorías con las categorías incluidas y los ajustes (mostrar, columnas, profundidad).
