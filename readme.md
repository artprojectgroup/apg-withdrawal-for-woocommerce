# WC - APG Desistimiento
Contributors: artprojectgroup

Donate link: https://artprojectgroup.es/tienda/donacion

Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights

Requires at least: 6.0

Tested up to: 7.0

Stable tag: 0.1.0

Requires PHP: 7.4

WC requires at least: 7.0

WC tested up to: 10.8.0

License: GNU General Public License v3 or later

License URI: https://www.gnu.org/licenses/gpl-3.0.html

Añade a WooCommerce un flujo de trabajo de desistimiento online con formulario de cliente, integración en Mi cuenta y registro de solicitudes en el panel de administración.

## Description
**WC - APG Desistimiento** añade a tu tienda WooCommerce un flujo de trabajo completo de desistimiento online conforme a la legislación de protección al consumidor de la UE.

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
Puedes obtener más información sobre **WC - APG Desistimiento** en nuestro [sitio web oficial](https://artprojectgroup.es/plugins-para-woocommerce/wc-apg-withdrawal) y seguir el desarrollo en [GitHub](https://github.com/artprojectgroup/wc-apg-withdrawal).

## Instalación
1. Instala el plugin de una de estas formas:
 * Sube la carpeta `wc-apg-withdrawal` al directorio `/wp-content/plugins/` vía FTP.
 * Sube el archivo ZIP completo vía *Plugins -> Añadir nuevo -> Subir* en el panel de administración de WordPress.
 * Busca **WC - APG Desistimiento** en *Plugins -> Añadir nuevo* y pulsa el botón *Instalar ahora*.
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
**WC - APG Desistimiento** es un plugin gratuito. **Art Project Group** no proporciona soporte técnico gratuito, pero ofrece un servicio de [soporte técnico](https://artprojectgroup.es/tienda/ticket-de-soporte) de pago para instalación y configuración.

## Changelog
### 0.1.0
* Versión inicial.

## Gracias
Gracias a todos los que usáis el plugin, ayudáis a mejorarlo, hacéis donaciones o nos animáis con vuestros comentarios.

Si te resulta útil, puedes apoyar su desarrollo con una [pequeña donación](https://artprojectgroup.es/tienda/donacion).

## Servicios externos
Este plugin se conecta a la API de plugins de WordPress.org para obtener información del plugin (como la valoración). Envía el slug del plugin al solicitar los datos. Más información: https://wordpress.org/about/privacy/
