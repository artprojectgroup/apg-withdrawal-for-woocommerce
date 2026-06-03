# APG Desistimiento para WooCommerce
Contributors: artprojectgroup

Donate link: https://artprojectgroup.es/tienda/donacion

Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights

Requires at least: 6.0

Tested up to: 7.0

Stable tag: 0.5.0

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
* Casilla opcional de renuncia al desistimiento por contenido digital en el checkout (tanto en el shortcode clásico como en el de bloques): un selector configurable permite elegir cuándo mostrarla — nunca, solo en productos virtuales (o con `_apg_withdrawal_type = digital` por producto), en todos los pedidos, o en categorías y/o productos seleccionados. La elección del cliente se guarda en los metadatos del pedido como evidencia legal.
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

### ¿Dónde debo colocar el enlace al formulario de desistimiento?
La página del formulario se crea automáticamente al activar el plugin y contiene el shortcode `[apg_withdrawal_form]`. Para cumplir el Artículo 11 bis de la Directiva 2011/83/UE (introducido por la Directiva 2023/2673), el enlace a esa página debe estar destacado y ser fácil de localizar en la tienda. El plugin pone a tu disposición varias herramientas para colocarlo; decidir *dónde* colocarlo es responsabilidad del comerciante (o de su diseñador web):

* La URL fija de la página auto-creada, disponible en *WooCommerce → Desistimiento → Página de desistimiento*.
* El shortcode `[apg_withdrawal_link]`, con atributos opcionales `label`, `class` y `target`, para insertar el enlace en cualquier entrada, página, widget de pie o bloque HTML.
* El bloque Gutenberg *Enlace de desistimiento* equivalente para sitios construidos con el Full Site Editor.
* La acción *Solicitud de desistimiento* que se añade automáticamente a cada pedido elegible en la tabla *Mi cuenta → Pedidos*.

Ubicaciones recomendadas habituales:

* El pie de página del sitio, para que el enlace sea accesible desde cualquier página.
* El menú de *Mi cuenta* (la acción por pedido ya está añadida; también puedes añadir un elemento de menú principal que enlace al formulario público).
* Las páginas de Términos y Condiciones / Política de Privacidad, junto con el resto de información al consumidor exigida por el Artículo 6.1.h de la Directiva 2011/83/UE.
* Los correos transaccionales de pedido en proceso / completado (el plugin ya inyecta el enlace ahí automáticamente vía `woocommerce_email_after_order_table`).

### ¿Durante cuánto tiempo debo conservar las solicitudes de desistimiento?
El plugin no elimina automáticamente las solicitudes de desistimiento registradas. Como recomendación general, consérvalas al menos **5 años** desde su creación — el plazo habitual de prescripción para acciones de consumo y contractuales en muchas jurisdicciones europeas. Comprueba siempre el plazo de conservación aplicable en tu país antes de eliminar registros antiguos o usar el flujo de exportación CSV y desinstalación del plugin.

### ¿Dónde puedo obtener soporte?
**APG Desistimiento para WooCommerce** es un plugin gratuito. **Art Project Group** no proporciona soporte técnico gratuito, pero ofrece un servicio de [soporte técnico](https://artprojectgroup.es/tienda/ticket-de-soporte) de pago para instalación y configuración.

## Changelog
### 0.5.0
* Cumplimiento de la Directiva (UE) 2023/2673 (que modifica la Directiva 2011/83/UE sobre derechos de los consumidores). El plugin pasa a cubrir las obligaciones adicionales introducidas por el nuevo Artículo 11 bis (función de desistimiento online) y los requisitos relacionados de información precontractual y carga de la prueba.
* Nuevo meta a nivel de término *Tipo de desistimiento* en `product_cat`, con herencia automática en los productos que mantienen el valor "Desistimiento permitido (por defecto)". Cuando un producto pertenece a varias categorías con tipos en conflicto, gana el tipo más restrictivo (orden de prioridad: `excluded` > `personalized` > `digital` > `manual` > `allowed`).
* Nuevo shortcode `[apg_withdrawal_notice]`, bloque Gutenberg `apg-withdrawal/notice` y enganche automático a `woocommerce_single_product_summary` con prioridad 20 (entre el precio y el botón Añadir al carrito) que muestran el aviso de exclusión en la ficha del producto cuando el tipo de desistimiento efectivo es distinto de `allowed`.
* Nueva sección de ajustes "Textos de aviso de exclusión" con una textarea editable por tipo no permitido (`excluded`, `digital`, `personalized`, `manual`) y un texto por defecto traducido para cada uno. Campo opcional por producto en la pestaña *Desistimiento* del producto para sobrescribir el aviso solo en ese producto.
* Sección "Renuncia al desistimiento de contenido digital" simplificada a un único selector excluyente con tres modos — `Nunca (desactivado)`, `En productos clasificados como contenido digital`, `En todos los pedidos` — gobernado exclusivamente por el tipo de desistimiento por producto/categoría. Las instalaciones con el modo `virtual` se migran a `digital`; las que estaban en `specific` se migran a `digital` y las categorías/productos previamente seleccionados se marcan automáticamente con `_apg_withdrawal_type = digital` para conservar el comportamiento. Los ajustes antiguos `digital_waiver_categories` / `digital_waiver_products` dejan de mostrarse (la migración única corre en `init`, marcada por la opción `apg_withdrawal_migrated_to_0_5`).
* Modelo de formulario de desistimiento del Anexo I.B imprimible servido en `?apg_withdrawal_model_form=1` con `@media print`, rellenado automáticamente con el nombre, dirección y correo electrónico de la tienda (de los ajustes de WooCommerce) y un teléfono del comerciante opcional (nuevo ajuste `Teléfono del comerciante (opcional)`). El formulario público de solicitud enlaza al modelo como "Descargar el modelo oficial de formulario de desistimiento (Anexo I.B)".
* Nuevo shortcode `[apg_withdrawal_link]` y bloque Gutenberg `apg-withdrawal/link` que renderizan un enlace al formulario público de desistimiento con atributos opcionales `label`, `class` y `target`. La etiqueta por defecto usa el texto literal sugerido por el Artículo 11 bis(1) ("Desistir del contrato aquí"). La acción por pedido de Mi cuenta pasa a usar ese mismo texto por defecto en instalaciones nuevas.
* El correo de acuse al cliente incluye ahora un hash SHA-256 verificable del contenido del acuse (calculado sobre nombre + email + pedido + alcance + productos + detalles + timestamp UTC) y el timestamp UTC utilizado para verificarlo. El hash y el timestamp también se persisten en post meta (`_apg_withdrawal_receipt_hash`, `_apg_withdrawal_receipt_hash_timestamp`) y se exponen en la exportación CSV.
* El consentimiento de la casilla de renuncia digital en el checkout se persiste ahora como log estructurado (`_apg_withdrawal_digital_waiver_log` en el meta del pedido) que incluye el texto exacto mostrado al cliente, timestamp UTC, IP, user agent y tipo de checkout (`classic` o `block`). El meta booleano legado `_apg_withdrawal_digital_waiver` se sigue escribiendo por retrocompatibilidad.
* Indicador de entrega de email: cada correo de cambio de estado y el acuse inicial al cliente registran ahora si se invocó `wp_mail()`, si la llamada devolvió éxito (= "aceptado por el mailer", no entrega real al destinatario), el timestamp UTC y cualquier error capturado vía `wp_mail_failed`. La información se muestra en la pantalla de detalle de la solicitud y se exporta como dos columnas adicionales del CSV.
* Integración RGPD: el plugin registra ahora un exportador y un borrador de datos personales con las herramientas nativas de privacidad de WordPress. El borrador **anonimiza** las solicitudes de desistimiento (sustituye nombre, correo electrónico, teléfono, IP, user agent y el texto libre del cliente por `[redactado]`) y conserva la solicitud y su referencia `_apg_withdrawal_wc_order_id` como evidencia legal, conforme a la carga de la prueba del Artículo 16 bis(8).
* La exportación CSV se defiende ahora contra la inyección de fórmulas en hojas de cálculo: a las celdas cuyo primer carácter es `=`, `+`, `-`, `@`, tabulador o retorno de carro se les antepone un apóstrofe antes de escribirlas con `fputcsv`.
* Nuevas entradas de FAQ que documentan dónde debe colocar el enlace al formulario de desistimiento el comerciante o su diseñador web y que recomiendan un plazo mínimo de conservación de 5 años para los registros de solicitudes de desistimiento.

### 0.4.0
* Nuevo ajuste "Texto personalizado de la casilla" en la sección Renuncia al desistimiento de contenido digital: permite sobrescribir el texto por defecto que se muestra en el checkbox del checkout con una cadena de texto plano. Si se deja vacío se conserva el texto traducible por defecto.
* La página por defecto que crea el plugin pasa a tener el título "Ejercer derecho de desistimiento" ("Exercise the right of withdrawal" en inglés) y deja que WordPress derive el slug del título automáticamente. Las páginas ya existentes no se modifican; solo se aplica a instalaciones nuevas.
* Interno: corregida la lista blanca de modos en el saneador de ajustes (`disabled`, `virtual`, `all`, `specific`) para que coincida con los valores reales del selector.

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
