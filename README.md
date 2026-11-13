# Auditoría WiFi

Plugin de auditoría de seguridad para GLPI 11 que registra cambios en los activos y en sus autorizaciones de acceso Wi-Fi.

- **Nombre visible:** Auditoría WiFi
- **Identificador técnico:** `auditoriawifi`
- **Repositorio:** https://github.com/fmosquera01/auditoriawifi
- **Rama de desarrollo:** `main`
- **Plataforma objetivo:** GLPI 11.0.11
- **Integración de notificaciones:** FML Telegram (`telegram`)

## Objetivo

Mantener una trazabilidad independiente de las alertas. Los cambios relevantes se registran en el historial aunque no estén habilitados para notificación. La configuración de alertas permite seleccionar los eventos que deben enviarse a Telegram por tipo de activo.

## Tipos de activos cubiertos

1. Computer
2. Printer
3. Phone
4. PluginGenericobjectMobile (Asset Definition Mobile)

La auditoría podrá habilitarse o deshabilitarse por tipo de activo. Deshabilitarla no elimina los registros históricos.

## Catálogo inicial de eventos

### Activo
- `asset.created`: creación del activo.
- `asset.deleted`: eliminación del activo.

### MAC / puertos
- `wifi.mac.added`: incorporación de una MAC.
- `wifi.mac.changed`: modificación de una MAC.
- `wifi.mac.removed`: eliminación de una MAC.
- `wifi.port.associated`: asociación de un puerto con un activo.
- `wifi.port.disassociated`: desasociación de un puerto de un activo.

### SSID / VLAN
- `wifi.ssid.added`: asignación de una red Wi-Fi.
- `wifi.ssid.changed`: cambio de red Wi-Fi.
- `wifi.ssid.removed`: retiro de la red Wi-Fi.
- `wifi.vlan.changed`: cambio del atributo RADIUS almacenado en el campo `comment` del desplegable SSID, incluso si su ID no cambia.

Los cambios de MAC y SSID se auditan como eventos distintos. Si ambos cambian durante una misma operación, se conservarán ambos detalles y la notificación podrá consolidarlos en un solo mensaje.

## Fuentes de datos Wi-Fi

- `glpi_networkports`
- `glpi_plugin_fields_computerwifis`
- `glpi_plugin_fields_printerwifis`
- `glpi_plugin_fields_phonewifis`
- `glpi_plugin_fields_glpicustomassetmobileassetwifis`
- `glpi_plugin_fields_ssidfielddropdowns`

La relación Mobile debe filtrarse por `itemtype = 'PluginGenericobjectMobile'` para evitar colisiones de `items_id` entre tipos de activo.

El valor `comment` del desplegable SSID es especialmente importante: es el dato que consume FreeRADIUS para determinar el atributo de red/VLAN. Se auditarán por separado el nombre legible del SSID y el valor RADIUS.

## Notificaciones

Las notificaciones se encolarán exclusivamente mediante la API pública de FML Telegram:

`GlpiPlugin\\Telegram\\NotificationService::enqueue()`

Este plugin no almacenará tokens, no implementará transporte HTTP hacia Telegram y no tendrá una cola de entrega propia. Un fallo de Telegram nunca debe interrumpir una operación de GLPI.

## Principios de auditoría

- Registrar quién realizó el cambio, cuándo, sobre qué activo y los valores anterior y nuevo.
- No generar eventos por estadísticas automáticas de interfaces.
- No confundir el alta del activo con el alta de una MAC.
- No confundir auditoría histórica con reglas de notificación.
- Conservar el historial al desactivar un tipo de activo.
- Tratar los cambios del atributo RADIUS del desplegable como cambios de seguridad, aunque el activo no se haya editado.

## Estado

Versión 0.1.1 en desarrollo: estructura del plugin, tablas de auditoría, panel inicial y hooks para altas/modificaciones/eliminaciones de activos, cambios de MAC y asociación/desasociación de puertos. La comparación de SSID mediante Fields está implementada a nivel inicial, pero aún debe validarse en GLPI real. La auditoría de cambios globales del atributo RADIUS, la configuración de alertas, las reglas obligatorias de Mobile y la integración con FML Telegram están pendientes. No instalar en producción.
