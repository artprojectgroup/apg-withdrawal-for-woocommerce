# APG Desistimiento para WooCommerce
Contributors: artprojectgroup

Donate link: https://artprojectgroup.es/tienda/donacion

Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights

Requires at least: 6.0

Tested up to: 7.0

Stable tag: 0.3.0

Requires PHP: 7.4

WC requires at least: 7.0

WC tested up to: 10.8.0

License: GNU General Public License v3 or later

License URI: https://www.gnu.org/licenses/gpl-3.0.html

Añade a WooCommerce un flujo de trabajo de desistimiento online con formulario de cliente, integración en Mi cuenta y registro de solicitudes en el panel de administración.

## Description
**APG Desistimiento para WooCommerce** añade a tu tienda WooCommerce un flujo de trabajo completo de desistimiento online conforme a la legislación de protección al consumidor de la UE.

### Características
* Formulario de desistimiento para el cliente mediante el shortcode `[apg_withdrawal_form]`.
* Ventana de desistimiento configurable (en días) y fuente del plazo (fecha de completado o fecha de creación del pedido).
* Días de cortesía adicionales sobre la ventana estándar.
* Detección de solicitud activa: oculta el botón de desistimiento si ya existe una solicitud abierta para el pedido.
* Registro de solicitudes en el panel de administración con todos los detalles (CPT).
* Opciones de almacenamiento de dirección IP e identificador del navegador para evidencia legal.
* Notificación por correo electrónico al administrador de la tienda en cada nueva solicitud.
* Correo electrónico de acuse de recibo automático al cliente al enviar el formulario.
* Correos electrónicos de actualización de estado al cliente cuando la solicitud es aceptada, rechazada o completada.
* Automatización: actualiza automáticamente el estado de la solicitud de desistimiento cuando el pedido de WooCommerce asociado cambia de estado.
* Integración en Mi cuenta: los clientes pueden consultar el historial de sus solicitudes de desistimiento.
* Exportación CSV de todas las solicitudes de desistimiento.
* 100% compatible con HPOS (High-Performance Order Storage).

### Traducciones
* *English*: por [**Art Project Group**](https://artprojectgroup.es/) (idioma por defecto).
* *Español*: por [**Art Project Group**](https://artprojectgroup.es/).

### Más información
Puedes obtener más información sobre **APG Desistimiento para WooCommerce** en nuestro [sitio web oficial](https://artprojectgroup.es/plugins-para-woocommerce/apg-withdrawal-for-woocommerce) y seguir el desarrollo en [GitHub](https://github.com/artprojectgroup/apg-withdrawal-for-woocommerce).

## Instalación
1. Instala el plugin de una de estas formas:
 * Sube la carpeta `apg-withdrawal-for-woocommerce` al directorio `/wp-content/plugins/` vía FTP.
 * Sube el archivo ZIP completo vía *Plugins -> Añadir nuevo -> Subir* en el panel de administración de WordPress.
 * Busca **APG Desistimiento para WooCommerce** en *Plugins -> Añadir nuevo* y pulsa el botón *Instalar ahora*.
2. Activa el plugin a través del menú *Plugins* en el panel de administración de WordPress.
3. Configura el plugin en *WooCommerce -> Desistimiento* o a través del enlace *Ajustes* en la página de plugins.
4. Añade el shortcode `[apg_withdrawal_form]` a la página configurada como página de desistimiento en los ajustes.

## Preguntas frecuentes
### ¿Cómo se configura el plugin?
En los ajustes del plugin puedes configurar el correo electrónico de notificación, la página de desistimiento, la ventana de desistimiento en días, la fuente del plazo (fecha de completado o fecha de creación del pedido), los días de cortesía adicionales y qué datos almacenar (dirección IP, identificador del navegador).

### ¿Es compatible con HPOS?
Sí. El plugin es totalmente compatible con WooCommerce High-Performance Order Storage.

### ¿Pueden los clientes invitados enviar una solicitud de desistimiento?
Sí. El formulario admite tanto clientes registrados (con datos rellenados previamente y selector de pedidos) como invitados (con búsqueda de pedidos por correo electrónico).

### ¿Dónde puedo obtener soporte?
**APG Desistimiento para WooCommerce** es un plugin gratuito. **Art Project Group** no proporciona soporte técnico gratuito, pero ofrece un servicio de [soporte técnico](https://artprojectgroup.es/tienda/ticket-de-soporte) de pago para instalación y configuración.

## Changelog
### 0.3.0
* Nuevo: casilla de renuncia al derecho de desistimiento para contenido digital en el checkout. Los clientes que compran contenido digital o servicios virtuales ven una aceptación opcional reconociendo que solicitar el suministro inmediato implica la pérdida del derecho de desistimiento (requisito de la legislación de protección al consumidor de la UE). La casilla es informativa: marcarla no es obligatorio ni bloquea el envío del pedido.
* La casilla se inyecta en ambos checkouts: shortcode clásico (vía `woocommerce_checkout_before_terms_and_conditions` con prioridad 999) y bloques (vía JavaScript que se reposiciona con `MutationObserver` para quedar siempre justo antes de la casilla nativa, tras cualquier otra personalizada).
* En el checkout de bloques se aplica una limpieza genérica que elimina el contenido inyectado junto a nuestro envoltorio por plugins de terceros con selectores demasiado amplios (por ejemplo, los que usan `.wp-block-woocommerce-checkout-terms-block .wc-block-components-checkbox` con jQuery `.after()`), evitando avisos de privacidad o marketing duplicados.
* La elección del cliente se guarda en el meta del pedido `_apg_withdrawal_digital_waiver` (`'1'` o `'0'`) en ambos checkouts: el clásico lee el valor del POST en `woocommerce_checkout_create_order`, y el de bloques inyecta el valor en el cuerpo de la petición de StoreAPI bajo `extensions['apg-withdrawal']['digital_waiver']`, que el hook `woocommerce_store_api_checkout_update_order_from_request` persiste con el mismo meta.
* El script del checkout de bloques reacciona a los cambios del carrito durante el flujo: observa las mutaciones de StoreAPI y, mediante un endpoint AJAX nonceado (`apg_withdrawal_check_cart_waiver`), revuelve a comprobar en el servidor si el carrito actual sigue calificando, mostrando o quitando la casilla sin recargar la página.
* Nueva sección de ajustes "Renuncia al desistimiento de contenido digital" con un selector SelectWoo único de cuándo mostrar la casilla: nunca (por defecto), solo en productos virtuales, en todos los pedidos, o en productos de categorías seleccionadas o productos seleccionados (estas dos opciones se pueden combinar). Los selectores de categorías y productos se muestran solo cuando son relevantes. El modo "Solo en productos virtuales" también detecta productos con el ajuste por producto `_apg_withdrawal_type = digital`, tratando ambos (la marca nativa de virtual de WooCommerce y la clasificación explícita como digital) como disparadores equivalentes.

### 0.2.0
* El formulario del frontend hereda ahora la hoja de estilo nativa de WooCommerce (avisos, campos, botones) sin necesidad de personalización CSS adicional.
* Los avisos se renderizan con `wc_print_notice()` para que adopten la plantilla correcta de WooCommerce tanto en temas de bloques (`block-notices/*.php`) como en temas clásicos (`notices/*.php`).
* Los avisos dinámicos (error de "pedido no encontrado" y aviso de producto) se pre-renderizan en el servidor con `wc_print_notice()` y solo se muestran/ocultan desde JavaScript, en lugar de construirse a mano con marcado legacy que se rompe en temas de bloques.
* El aviso de "pedido no encontrado" sigue el patrón nativo de WooCommerce: aviso al inicio del formulario más la clase `woocommerce-invalid` en el campo de correo electrónico.
* Los botones usan `wc_wp_theme_get_element_class_name( 'button' )` para compatibilidad con temas y temas de bloques.
* Se ha eliminado el CSS en línea inyectado desde JavaScript en favor de las clases nativas de avisos de WooCommerce.
* Traducción al español actualizada al tratamiento informal "tú" conforme al libro de estilo de WooCommerce.

### 0.1.0
* Versión inicial.

## Gracias
Gracias a todos los que usáis el plugin, ayudáis a mejorarlo, hacéis donaciones o nos animáis con vuestros comentarios.

Si te resulta útil, puedes apoyar su desarrollo con una [pequeña donación](https://artprojectgroup.es/tienda/donacion).

## Servicios externos
Este plugin se conecta a la API de plugins de WordPress.org para obtener información del plugin (como la valoración). Envía el slug del plugin al solicitar los datos. Más información: https://wordpress.org/about/privacy/
