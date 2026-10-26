# APG Desistimiento para WooCommerce
Contributors: artprojectgroup

Donate link: https://artprojectgroup.es/tienda/donacion

Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights

Requires at least: 6.0

Tested up to: 7.2

Stable tag: 0.7.4

Requires PHP: 7.4

WC requires at least: 7.0

WC tested up to: 11.1.2

License: GNU General Public License v3 or later

License URI: https://www.gnu.org/licenses/gpl-3.0.html

Añade a WooCommerce un flujo de trabajo de desistimiento online con formulario de cliente, integración en Mi cuenta y registro de solicitudes en el panel de administración.

## Description
**APG Desistimiento para WooCommerce** añade a tu tienda WooCommerce un flujo de trabajo completo de desistimiento online conforme a la legislación de protección al consumidor de la UE.

### Características
* Formulario de desistimiento para el cliente mediante el shortcode `[apg_withdrawal_form]`.
* Desistimiento parcial hasta la unidad: el cliente marca las líneas del pedido y ajusta de cuántas unidades de cada una desiste.
* Ventana de desistimiento configurable (en días) y fuente del plazo (fecha de completado o fecha de creación del pedido).
* Días de cortesía adicionales sobre la ventana estándar.
* Detección de solicitud activa: oculta el botón de desistimiento si ya existe una solicitud abierta para el pedido.
* Casilla opcional de renuncia al desistimiento por contenido digital en el checkout (tanto en el shortcode clásico como en el de bloques): un único selector excluyente permite elegir cuándo mostrarla — nunca, en productos clasificados como contenido digital, o en todos los pedidos. **Qué productos cuentan lo decide exclusivamente el "Tipo de desistimiento" por producto y por categoría, nunca si el producto es virtual o descargable**: se lee primero el tipo del propio producto y, cuando mantiene el valor por omisión `allowed`, se hereda de sus categorías quedándose con el más restrictivo. La elección del cliente se guarda en los metadatos del pedido como log estructurado —el texto exacto mostrado, marca de tiempo UTC, IP, user agent y tipo de checkout— como evidencia legal.
* Registro de solicitudes en el panel de administración con todos los detalles (CPT), vistas por estado con contadores, cambios de estado en lote y columna de estado ordenable.
* Estado de pedido propio de WooCommerce *Desistimiento solicitado*, aplicable opcionalmente al pedido en cuanto se recibe una declaración, para poder filtrar, ordenar y editar en lote los pedidos afectados con las herramientas nativas.
* Cuenta para el reembolso (IBAN) opcional, validada con los dígitos de control internacionales (ISO 7064 MOD-97-10).
* Doble confirmación opcional para invitados: la solicitud queda retenida hasta que el cliente abre un enlace de un solo uso enviado a la dirección del pedido, conservando la fecha y la hora del envío original.
* Ajuste de jurisdicción (base UE / España) que determina la referencia normativa que se muestra al cliente.
* API REST bajo `apg-withdrawal/v1` para listar, consultar, crear y tramitar solicitudes desde tiendas headless e integraciones de back-office.
* Opciones de almacenamiento de dirección IP e identificador del navegador para evidencia legal.
* Notificación por correo electrónico al administrador de la tienda en cada nueva solicitud.
* Correo electrónico de acuse de recibo automático al cliente al enviar el formulario.
* Correos electrónicos de actualización de estado al cliente cuando la solicitud es aceptada, rechazada o completada.
* Automatización: actualiza automáticamente el estado de la solicitud de desistimiento cuando el pedido de WooCommerce asociado cambia de estado.
* Integración en Mi cuenta: los clientes pueden consultar el historial de sus solicitudes de desistimiento.
* Exportación CSV de las solicitudes de desistimiento, respetando el estado seleccionado en el listado, con protección frente a la inyección de fórmulas en hojas de cálculo.
* 100% compatible con HPOS (High-Performance Order Storage).
* Aviso cuando la función de desistimiento propia de WooCommerce 11.1 está activada a la vez, para que la tienda no acabe ofreciendo a los clientes dos formularios que no se hablan entre sí.
* Formulario público construido conforme a WCAG 2.1 AA —el nivel que exige hoy la EN 301 549— y cumpliendo ya el criterio de tamaño de objetivo de WCAG 2.2: controles agrupados y etiquetados, regiones activas para los avisos dinámicos y foco desplazado a cada paso nuevo.
* Reembolso de WooCommerce en un clic a partir de la solicitud: las líneas y cantidades declaradas, limitadas por lo que quede por reembolsar en el pedido. Siempre se inicia a mano, nunca automáticamente. Funciona con Redsys y Bizum sin configuración adicional.
* Preparado para WPML y Polylang, tanto mediante `wpml-config.xml` como mediante el registro de cadenas en tiempo de ejecución.

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

### WooCommerce 11.1 trae su propia función de desistimiento. ¿Puedo usar las dos?
Poder, puedes, pero no deberías. WooCommerce 11.1 añadió *Desistimiento de pedido* en WooCommerce → Ajustes → Avanzado → Características, desactivada de serie. Con las dos en marcha, tus clientes llegan a dos formularios distintos en dos direcciones distintas, cada uno con su propio registro y su propio acuse de recibo, y ninguno sabe del otro: la misma declaración puede quedar registrada dos veces y contestada dos veces. El plugin detecta la característica de WooCommerce y te avisa, con un enlace a la pantalla donde se desactiva.

La función del núcleo cubre lo básico: un formulario, un paso de revisión, los correos de acuse, la correspondencia con el pedido y una nota en él. Este plugin añade lo que una tienda necesita alrededor: el tipo de desistimiento por producto y por categoría con herencia y el aviso de exclusión en la ficha, el consentimiento del artículo 16(m) para contenido digital al finalizar la compra con su registro estructurado, el modelo del anexo I.B imprimible, el hash SHA-256 del acuse para la carga de la prueba del artículo 16 bis(8), el panel de reembolso de WooCommerce guiado por las líneas declaradas, el exportador, el borrador y la anonimización del RGPD, y la API REST. Elige una de las dos y desactiva la otra.

### ¿Pueden los clientes invitados enviar una solicitud de desistimiento?
Sí. Quien tiene la sesión iniciada encuentra sus datos rellenados y elige en un desplegable entre sus propios pedidos. Quien entra como invitada escribe el número de pedido y la dirección de correo que consta en él, que es el par que el plugin valida antes de aceptar cualquier declaración, la misma prueba que pide el seguimiento de pedidos de WooCommerce. El plugin nunca enumera los pedidos de un cliente a quien solo ha facilitado una dirección de correo.

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

### ¿Qué pasa si mi tienda no ofrece la función de desistimiento?
El artículo 11 bis de la Directiva 2011/83/UE se aplica en toda la UE desde el 19 de junio de 2026, con independencia de que cada Estado miembro lo haya transpuesto. España no lo había hecho en la fecha de esta versión —la Comisión Europea emitió un dictamen motivado contra España en junio de 2026—, pero la obligación se aplica por efecto directo y el incumplimiento se sanciona por el régimen general del TRLGDCU, cuyas multas llegan a 100.000 € en infracciones leves, 1.000.000 € en las graves y 4.000.000 € (o el 4 % del volumen de negocio en España, si esta cifra es superior) en las muy graves. Consulta el régimen aplicable en tu país: esta nota es informativa y no constituye asesoramiento jurídico.

### ¿Puedo pedirle al cliente una cuenta bancaria?
Sí, en *WooCommerce → Desistimiento → Gestión de solicitudes → Cuenta para el reembolso (IBAN)*, como campo opcional o como campo obligatorio. Viene desactivado. El número se valida con los dígitos de control internacionales (ISO 7064 MOD-97-10) antes de aceptarse, se guarda junto a la solicitud, se incluye en el exportador de privacidad y el borrador de datos personales lo elimina por completo. Actívalo solo si realmente no puedes reembolsar por el medio de pago original.

### ¿Cómo evito que otra persona presente un desistimiento en nombre de un cliente?
Activa *¿Confirmar por correo electrónico las solicitudes de invitados?*. A partir de ese momento, la solicitud de un visitante que no ha iniciado sesión queda retenida mientras se envía un enlace de un solo uso a la dirección registrada en el pedido; la declaración solo se registra al abrirlo. La solicitud conserva la fecha y la hora del envío original, así que el cliente no pierde días de su plazo de desistimiento. Los clientes con sesión iniciada no se ven afectados, porque WordPress ya los ha autenticado.

### ¿Es accesible el plugin? ¿Ayuda con el Acta Europea de Accesibilidad?
El formulario público se ha construido con el objetivo de **WCAG 2.1 nivel AA**, que es lo que exige hoy la EN 301 549, la norma a la que remite el Acta Europea de Accesibilidad: el selector de líneas es un `fieldset` etiquetado de casillas y campos numéricos nativos manejable con el teclado, los avisos dinámicos son regiones activas y el foco se desplaza a cada paso nuevo del flujo. Cumple además el criterio de tamaño de objetivo de WCAG 2.2 (2.5.8), que llega con la versión 4.1.1 de esa norma, y el enlace de confirmación de invitados es justamente el patrón que WCAG 2.2 recomienda para la autenticación accesible (3.3.8), porque no plantea al cliente ninguna prueba cognitiva.

Dos matices. El primero: el Acta de Accesibilidad (Directiva (UE) 2019/882, en España la Ley 11/2023) se aplica al comercio electrónico **desde el 28 de junio de 2025** y obliga a *la tienda*, no a un plugin aislado: el contraste de color, la visibilidad del foco y la estructura de la página los pone tu tema, y la responsabilidad del conjunto es del comerciante. El segundo: las empresas con menos de 10 empleados **y** un volumen de negocio inferior a 2 millones de euros están exentas de las obligaciones sobre servicios. Comprueba tu caso: esta nota es informativa y no constituye asesoramiento jurídico.

### ¿Puedo reembolsar al cliente desde la solicitud de desistimiento?
Sí. Cada solicitud muestra un panel *Reembolso* con el importe que implica la declaración —las líneas declaradas con sus cantidades, más el coste del envío estándar cuando el desistimiento abarca todo el contrato, como exige el artículo 13.1 de la Directiva 2011/83/UE—, siempre limitado por lo que quede por reembolsar en el pedido, de modo que un reembolso que ya hayas hecho a mano nunca se entrega dos veces. No ocurre nada hasta que pulsas el botón. Por defecto el reembolso solo se *registra* en WooCommerce, sin mover dinero ni tocar el stock; dos casillas permiten enviarlo por la pasarela de pago (cuando la pasarela lo admite) y reponer el stock de los productos devueltos. El filtro `apg_withdrawal_refund_plan` permite ajustar las cifras, por ejemplo para descontar la depreciación de los bienes devueltos.

### ¿Funciona con Redsys y Bizum?
Sí, y no hay que instalar nada más. Bizum funciona sobre Redsys en comercio electrónico, y las implementaciones de Redsys para WooCommerce —la pasarela de pago de José Conti, la de Codection, el módulo unificado oficial— tramitan las devoluciones a través de la API estándar de reembolsos de WooCommerce. El panel de reembolso usa esa misma API, así que marcar *Devolver el dinero por Redsys* llega directamente a tu TPV virtual. El panel indica con nombre y apellidos la pasarela que va a usar, para que sepas siempre a dónde va el dinero.

Tres cosas que conviene saber. Redsys admite **devoluciones totales y parciales**, así que un desistimiento parcial se devuelve tal y como se declaró. **Cuándo ve el cliente el dinero depende de su banco**, normalmente entre 2 y 31 días: el plazo de 14 días del artículo 13.1 de la Directiva 2011/83/UE te obliga a *emitir* el reembolso, no obliga al banco del cliente a abonarlo. Y si usas una pasarela de Redsys que no implementa devoluciones, como la edición Light gratuita, el panel te lo dice y registra el reembolso en WooCommerce sin mover dinero, para que lo emitas desde el back office de tu banco y la contabilidad cuadre.

Cuando una pasarela rechaza un reembolso, el motivo que da —el código SIS, en el caso de Redsys— aparece en el aviso de error en lugar de un fallo genérico, para que tengas algo sobre lo que actuar.

### ¿El plugin tiene API REST?
Sí, bajo el espacio de nombres `apg-withdrawal/v1`. `GET /requests` y `GET /requests/<id>` listan y consultan solicitudes, y `PUT /requests/<id>` cambia su estado; las tres requieren la capacidad `manage_woocommerce`. `POST /requests` registra una declaración procedente de una tienda headless y es pública, igual que el formulario público: pasa por el mismo procesador, de modo que el pedido debe corresponder con la dirección de correo electrónico, se aplica el limitador de intentos y, si la has activado, entra en juego la verificación por correo electrónico de invitados.

### ¿Durante cuánto tiempo debo conservar las solicitudes de desistimiento?
El plugin no elimina automáticamente las solicitudes de desistimiento registradas. Como recomendación general, consérvalas al menos **5 años** desde su creación — el plazo habitual de prescripción para acciones de consumo y contractuales en muchas jurisdicciones europeas. Comprueba siempre el plazo de conservación aplicable en tu país antes de eliminar registros antiguos o usar el flujo de exportación CSV y desinstalación del plugin.

### ¿Dónde puedo obtener soporte?
**APG Desistimiento para WooCommerce** es un plugin gratuito. **Art Project Group** no proporciona soporte técnico gratuito, pero ofrece un servicio de [soporte técnico](https://artprojectgroup.es/tienda/ticket-de-soporte) de pago para instalación y configuración.

## Changelog
### 0.7.4
* Una solicitud de desistimiento ya registrada deja de registrarse por segunda vez cuando el cliente envía el formulario más de una vez.

### 0.7.3
* La pestaña "Desistimiento" de Mi cuenta abre ahora con su propio título, como el resto de las secciones de la cuenta, en lugar de empezar directamente con un aviso. Nuevo filtro `apg_withdrawal_account_title` para cambiarlo o quitarlo.
* El formulario carga ya su hoja de estilos cuando se inserta con el shortcode en un widget o en una plantilla de bloques, donde antes pedía un handle que solo existe en la administración y se quedaba sin estilos.
* Compatibilidad con WooCommerce 11.1.2 verificada.

### 0.7.2
* El formulario público ya no deja a la vista pedidos ajenos: una dirección de correo en la URL de la página listaba el historial de compras de esa persona, y un número de pedido por sí solo mostraba su contenido y su importe.
* Las solicitudes pasan a los permisos de pedidos de WooCommerce, así que el perfil Editor ya no puede leer ni modificar los datos del consumidor que contienen.
* El límite de intentos ya no se esquiva falseando una cabecera de proxy, y la pantalla de revisión pasa a tener su propio techo.
* La pantalla de revisión vuelve a funcionar sin JavaScript: se imprimía con un filtro que elimina los formularios y dejaba al cliente sin nada que enviar.
* La Edición rápida de una categoría de producto ya no restablece su tipo de desistimiento a "permitido".
* Los avisos de resultado de las acciones en lote y del reembolso los firma la redirección que los emite y se comprueban antes de mostrarse.
* Rehecho el escapado de salida y la verificación de nonces: el plugin ya no lleva ninguna excepción de seguridad marcada sobre su salida.
* Aviso cuando la función de desistimiento propia de WooCommerce 11.1 está activada a la vez, con enlace a la pantalla donde se desactiva, y nueva entrada del FAQ sobre qué cubre cada una.
* Compatibilidad con WooCommerce 11.1 verificada.

### 0.7.1
* Traducción al español revisada frente a la redacción aprobada en translate.wordpress.org: 27 cadenas corregidas de terminología y ortografía —"artículo" en minúscula, como pide el español; "al finalizar la compra" en lugar del anglicismo "checkout"; "servicio de envío" en lugar de "mailer"; "solo" sin la tilde que suprimió la RAE—. Como desde la 0.6.1 la traducción empaquetada tiene preferencia sobre el paquete de idioma centralizado, estas correcciones solo llegan a la tienda mediante una actualización del plugin, que es para lo que sirve esta versión. El modelo de formulario de desistimiento del Anexo I.B se ha dejado deliberadamente sin tocar, a la espera de contrastar su redacción con el Anexo B del TRLGDCU. Sin cambios en el código.

### 0.7.0
* Desistimiento parcial hasta la unidad. El alcance "Solo productos concretos" deja de comportarse como un selector de todo o nada por línea: el formulario muestra ahora una fila por línea del pedido con una casilla y un campo de cantidad limitado a las unidades originalmente compradas, de modo que quien compró tres unidades y devuelve una declara exactamente eso. Las unidades se guardan en `_apg_withdrawal_quantities`, viajan por la pantalla de confirmación y se muestran en el correo de acuse de recibo, en la ficha de la solicitud y en la exportación CSV como "Producto x 1 de 3". Además se incorporan al hash SHA-256 del acuse como pares `línea:unidades`, para que el resumen criptográfico cubra las cantidades y no solo qué líneas se seleccionaron. Las solicitudes registradas antes de esta versión siguen funcionando y se leen como "todas las unidades de las líneas seleccionadas", que es lo que significaban.
* Flujo de estados en el listado de solicitudes. Las vistas por estado de publicación ("Todo | Publicado"), que no significaban nada para este tipo de contenido, se sustituyen por un enlace por cada estado de desistimiento con su contador, resuelto en una única consulta agrupada. La columna de estado pasa a ser ordenable, una acción en lote por estado permite tramitar varias solicitudes a la vez —pasando por `apg_withdrawal_change_status()`, así que cada una sigue generando su entrada en el historial y, si está activado, su aviso al cliente— y el botón *Exportar CSV* exporta ahora el estado que se está viendo en pantalla en lugar de todo el registro.
* Estado de pedido propio de WooCommerce `wc-apg-withdrawal` ("Desistimiento solicitado"), registrado tanto para el almacenamiento clásico como para HPOS, disponible en el desplegable del editor de pedidos, en las vistas del listado de pedidos, como acción en lote y dentro de la propia automatización del plugin. Un ajuste nuevo, *Estado del pedido al recibir una solicitud*, mueve opcionalmente el pedido asociado a cualquier estado —el nuevo o uno existente— en cuanto se registra una declaración. Viene desactivado, así que actualizar no cambia nada hasta que lo actives.
* Cuenta para el reembolso (IBAN) opcional en el formulario público, configurable como oculta (por defecto), opcional u obligatoria. El número se normaliza y se comprueba contra la estructura ISO 13616 y los dígitos de control ISO 7064 MOD-97-10 antes de aceptarse, y aparece en la pantalla de confirmación, en el correo de acuse de recibo, en la ficha de la solicitud y en la exportación CSV. El exportador de privacidad lo incluye y el borrador, a diferencia del resto de campos personales, lo elimina en lugar de redactarlo: un marcador dentro de un campo de IBAN se leería como un número de cuenta mal formado, y la cuenta no tiene valor probatorio de la declaración en sí.
* Doble confirmación opcional para las declaraciones de invitados. El formulario público ya rechaza cualquier pedido cuya dirección de facturación no coincida con la que teclea el visitante, pero en una compra como invitado ese par puede ser conocido por alguien distinto del consumidor. Con *¿Confirmar por correo electrónico las solicitudes de invitados?* activado, el envío de un visitante sin sesión iniciada se retiene en el servidor y se manda un enlace de un solo uso a la dirección del pedido; la declaración solo se registra al abrirlo. Y lo esencial: el momento que cuenta jurídicamente es aquel en el que el consumidor envió el formulario, así que la marca de tiempo original viaja dentro del contenido retenido y se convierte en la fecha de la solicitud y en la marca temporal que cubre el hash del acuse — el cliente no pierde días de su plazo por culpa de su bandeja de entrada. Los tokens se guardan como resúmenes SHA-256, son de un solo uso y caducan a las 24 horas (filtrable mediante `apg_withdrawal_verification_ttl`).
* Capa de jurisdicción. Un ajuste nuevo, *Jurisdicción* (base `EU` / `ES`), determina la referencia normativa que se muestra en la pantalla de confirmación y en el correo de acuse de recibo. España todavía no ha transpuesto la Directiva (UE) 2023/2673, así que ambas opciones aplican hoy los mismos requisitos y la española cita además el TRLGDCU; el sentido de la capa es que toda ramificación por jurisdicción pase por `apg_withdrawal_jurisdiction_supports()`, para poder activar los añadidos nacionales en un único punto cuando lleguen al BOE en vez de repartirlos por el código. Tanto la jurisdicción como la matriz de requisitos son filtrables.
* API REST bajo el espacio de nombres `apg-withdrawal/v1`. `GET /requests` (paginada y filtrable por estado y pedido), `GET /requests/<id>` y `PUT /requests/<id>` requieren `manage_woocommerce`; `POST /requests` es pública para que una tienda desacoplada pueda registrar una declaración, y pasa exactamente por el mismo procesador que el formulario público — correspondencia pedido/correo electrónico, limitador de intentos y, si está activada, verificación por correo electrónico de invitados —, de modo que un envío retenido responde `202` con `pending_verification`. La cuenta para el reembolso solo se devuelve a quien puede gestionar WooCommerce. Se añade la acción `apg_withdrawal_request_registered`, que se dispara cuando una declaración queda completamente registrada.
* Reembolso al cliente directamente desde la solicitud de desistimiento. Cada solicitud incorpora un panel *Reembolso* que calcula lo que implica la declaración —las líneas declaradas con sus cantidades, más el coste del envío estándar cuando el desistimiento abarca todo el contrato, como exige el artículo 13.1 de la Directiva 2011/83/UE— y limita cada cifra por lo que quede por reembolsar en el pedido, de modo que un reembolso hecho a mano antes no se puede duplicar. Nunca se reembolsa nada automáticamente: lo lanza el comerciante, y por defecto el reembolso solo se registra en WooCommerce sin mover dinero ni tocar el stock, con casillas opcionales para enviarlo por la pasarela de pago (que solo se ofrecen si la pasarela admite reembolsos) y para reponer el stock. Crearlo exige la misma capacidad que pide WooCommerce para reembolsar un pedido, deja nota en el pedido y es filtrable mediante `apg_withdrawal_refund_plan`.
* Redsys y Bizum llegan al panel de reembolso sin integración adicional, porque esas pasarelas implementan la API estándar de reembolsos de WooCommerce y el panel se apoya en esa API. El panel indica ahora con nombre la pasarela que va a usar, en lugar de hablar de una "pasarela de pago" anónima; detalla para Redsys y Bizum que se admiten devoluciones parciales y que el banco del cliente tarda entre 2 y 31 días en abonar el dinero —el plazo del artículo 13.1 obliga al comerciante a emitir el reembolso, no al banco a abonarlo—; y, cuando la pasarela activa no puede devolver por programa (la edición Light gratuita de Redsys, por ejemplo), lo advierte y registra el reembolso sin mover dinero. La detección de pasarela es filtrable mediante `apg_withdrawal_gateway_info`.
* Un reembolso que la pasarela rechaza muestra ahora el motivo que ha dado la pasarela —el código SIS, en el caso de Redsys— en lugar de un aviso de error genérico.
* Corregido: el modelo imprimible del Anexo I.B no dejaba sitio para escribir. La altura mínima estaba en el contenedor del campo, que ya llenaba el propio rótulo, así que cada raya acababa pegada a su etiqueta. Ahora el espacio de escritura pertenece a las propias rayas y cada campo recibe tantas como necesita su respuesta: tres para la descripción del bien y para el domicilio del consumidor, dos para la firma y una para las fechas y el nombre.
* Corregido: la página del Anexo I.B ya no abre por su cuenta el diálogo de impresión. Imprimir automáticamente al cargar era a la vez un cambio de contexto no solicitado y el origen de un fallo de Safari por el que, tras cancelar una impresión, el navegador seguía imprimiendo hojas en blanco. Ahora la impresión parte siempre del botón, después de que se hayan asentado las tipografías web y un repintado. Un repintado en `afterprint` —y al dejar de cumplirse la consulta de medios de impresión, porque Safari es inconsistente sobre cuál de las dos emite al cerrar el diálogo— corrige también que la ventana se quedase en blanco al cerrarlo. El enlace del formulario público pasa a llamarse "Abrir el modelo de formulario" y anuncia que se abre en una pestaña nueva.
* Retoques de impresión del Anexo I.B: caja de página A4 con márgenes de 16 mm y rótulos que no se separan de sus rayas al saltar de hoja.
* Revisión de accesibilidad del formulario público, con WCAG 2.1 AA como objetivo. El selector de líneas es ahora un `fieldset` etiquetado de casillas y campos numéricos nativos en lugar de un desplegable múltiple, así que se maneja con el teclado y lo anuncian los lectores de pantalla; los avisos dinámicos pasan a ser regiones activas (`role="alert"` para el error de pedido, `role="status"` para el aviso de producto); y el foco se desplaza al contenido recién insertado tras cada paso AJAX, que hasta ahora lo dejaba sobre un botón que ya no existía (3.2.2 y 4.1.3). Las filas de líneas se espacian además de forma que cumplen ya el criterio de tamaño de objetivo de WCAG 2.2 (2.5.8), que no forma parte de WCAG 2.1 AA pero llega con la versión 4.1.1 de la EN 301 549, la norma a la que remite el Acta Europea de Accesibilidad y que obliga al comercio electrónico desde el 28 de junio de 2025.
* Se incluye `wpml-config.xml` en el plugin, de modo que WPML y Polylang detectan las seis cadenas configurables por el comerciante y los metadatos de tipo de desistimiento sin necesidad de que se ejecute código. El registro en tiempo de ejecución mediante `wpml_register_single_string` añadido en 0.6.0 se mantiene para las instalaciones que leen las cadenas por esa vía.
* Compatibilidad declarada con WooCommerce 11.0.
* Nuevas preguntas frecuentes sobre el régimen sancionador por no ofrecer la función de desistimiento, la cuenta para el reembolso, la doble confirmación de invitados y la API REST.

### 0.6.1
* La traducción empaquetada con el plugin pasa a tener prioridad sobre el paquete de idioma centralizado: cuando el plugin viaja con un `.mo` (o `.l10n.php`) para el locale actual bajo `/languages`, el filtro `lang_dir_for_domain` devuelve esa carpeta sin condiciones, en lugar de hacerlo solo cuando WordPress no encuentre nada en `wp-content/languages/plugins/`. Resultado: el locale empaquetado por el plugin (actualmente `es_ES`) siempre se renderiza con las cadenas incluidas en la versión publicada, así que los textos nuevos y los retoques del changelog llegan a los usuarios el mismo día que se publica el plugin, sin esperar a que translate.wordpress.org regenere su paquete de idioma. Los locales para los que el plugin no empaqueta traducción se siguen cargando desde el paquete centralizado del sistema, igual que antes.

### 0.6.0
* Corregido: la traducción al español empaquetada con el plugin (y cualquier futuro idioma que viaje bajo `/languages`) no se cargaba en los sitios para los que translate.wordpress.org aún no había generado un paquete de idioma. El cargador just-in-time de WordPress solo inspecciona `wp-content/languages/plugins/`, así que el `.mo` incluido dentro del plugin se ignoraba. Ahora el plugin se engancha al filtro `lang_dir_for_domain` introducido en WordPress 6.6 para devolver la carpeta `/languages` empaquetada cuando no haya paquete de idioma disponible, sin recurrir a la llamada `load_plugin_textdomain()` desaconsejada por WP.org. En WordPress 6.5 y anteriores la traducción empaquetada seguirá sin cargarse hasta que se publique un paquete de idioma, pero el plugin sigue funcionando en el idioma de origen.
* Integración con el log de emails transaccionales de WooCommerce 10.9: las tres clases `WC_Email` del plugin (acuse al cliente, notificación al administrador, cambio de estado) las recoge automáticamente el nuevo `EmailLogger` integrado. Cada entrada del log se enriquece mediante `woocommerce_email_log_context` con el ID de la solicitud de desistimiento, el alcance y el hash SHA-256 del acuse junto con su timestamp UTC, de forma que la entrada del log se puede contrastar con el email realmente entregado al cliente. El filtro se ignora silenciosamente en versiones anteriores de WooCommerce.
* Red de seguridad para el etiquetado del Artículo 11 bis: un nuevo helper (`apg_withdrawal_label_is_ambiguous()`) detecta etiquetas de desistimiento que no cumplen el requisito de redacción inequívoca del Artículo 11 bis de la Directiva 2011/83/UE. Se utiliza en dos sitios: (a) el texto configurable del botón de confirmación (`Ajustes → Texto del botón`) muestra ahora un aviso en el admin si el comerciante guarda un texto ambiguo (por ejemplo "Contáctanos", "Gestionar pedido" o "Volver"); la elección no se bloquea, solo se señala; (b) el shortcode `[apg_withdrawal_link]` y el bloque `apg-withdrawal/link` lanzan `_doing_it_wrong()` (visible con `WP_DEBUG`) cuando se usa un atributo `label` ambiguo. Las listas de términos "válidos" y "vetados" se pueden personalizar vía los filtros `apg_withdrawal_label_unambiguous_terms` y `apg_withdrawal_label_ambiguous_terms`.
* Rediseño del enlace al Anexo I.B en el formulario público: se sustituye el enlace largo inline por una frase descriptiva corta ("Si lo prefieres, puedes utilizar el modelo oficial (Anexo I.B).") y un botón "Imprimir" estilado con las clases nativas de botón de WooCommerce. El botón añade `?print=1` a la URL del Anexo I.B para que el diálogo de impresión del navegador se abra automáticamente al cargar la página (el botón "Imprimir o guardar como PDF" ya existente en la propia página sigue funcionando para quien llegue a la URL directamente).
* Mejora del bloque del destinatario en el Anexo I.B: el estado y el país del comerciante se muestran ahora con sus nombres legibles (vía `WC()->countries->get_countries()` y `get_states()`) en lugar del código ISO `XX:YY` que almacena `woocommerce_default_country`, cada uno en su propio párrafo. Todos los párrafos del bloque terminan en punto, como corresponde a una comunicación formal.
* Integración con WPML / Polylang para los textos configurables por el comerciante: el texto del botón de confirmación, la etiqueta personalizada de la renuncia al desistimiento de contenido digital y los cuatro avisos de exclusión por tipo se registran ahora con String Translation de WPML (Polylang incluye una capa de compatibilidad para la misma acción `wpml_register_single_string`) en el hook `init`. Cuando ninguno de los dos plugins está activo, el registro es un no-op y se renderiza el valor original sin cambios.
* Rate limiting + honeypot en el formulario público: nuevo helper `apg_withdrawal_is_rate_limited()` que limita los envíos repetidos por par IP + email (política por defecto: 5 intentos cada 10 minutos, ambos valores filtrables vía `apg_withdrawal_rate_limit_max` y `apg_withdrawal_rate_limit_window`). Se renderiza un campo honeypot oculto en los dos pasos del formulario que descarta silenciosamente los envíos automatizados antes de persistir nada. La detección de la IP del cliente respeta las cabeceras comunes de proxies inversos y es filtrable mediante `apg_withdrawal_client_ip`.
* Verificada la visibilidad continua del Artículo 11 bis durante los 14 días: la acción "Desistir del contrato aquí" en Mi cuenta → Pedidos se muestra para todo pedido cuyo plazo de desistimiento siga abierto, con independencia del estado de WooCommerce, cayendo automáticamente de `completed_date` a `created_date` para pedidos aún sin marcar como completados. No requiere cambio de código.

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
* Integración RGPD: el plugin registra ahora un exportador y un borrador de datos personales con las herramientas nativas de privacidad de WordPress. El borrador **anonimiza** las solicitudes de desistimiento (sustituye nombre, correo electrónico, teléfono, IP, user agent y el texto libre del cliente por `[redactado]`) y conserva la solicitud y su referencia `_apg_withdrawal_wc_order_id` como evidencia legal, conforme a la carga de la prueba del Artículo 16 bis(8). La misma anonimización se dispara automáticamente cuando se elimina un usuario de WordPress (desde *Usuarios → Borrar*, desde un botón "Eliminar mi cuenta" añadido por plugins de terceros como `apg-gdpr-texts-for-forms`, o desde cualquier otro flujo), evitando que los registros de desistimiento sobrevivan al usuario con datos personales.
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
