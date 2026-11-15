# Auditoría WiFi

Plugin de auditoría para GLPI 11 orientado a la trazabilidad de activos que participan en la autenticación Wi-Fi/RADIUS de Songa.

- **Nombre:** Auditoría WiFi
- **Identificador:** `auditoriawifi`
- **Repositorio:** https://github.com/fmosquera01/auditoriawifi
- **Rama de desarrollo:** `main`
- **Versión actual:** 0.1.24
- **Dependencia de notificaciones:** FML Telegram (`telegram`)

## Activos incluidos

El alcance se limita exclusivamente a:

| Nombre | Itemtype GLPI |
|---|---|
| Computadoras | `Computer` |
| Impresoras | `Printer` |
| Teléfonos | `Phone` |
| Mobile | `Glpi\CustomAsset\mobileAsset` |

No se auditan otros Asset Definitions.

## Auditoría

Los eventos se guardan en el historial independientemente de que estén seleccionados para notificación.

- Activos: creación, cambio de nombre, eliminación y purga.
- MAC: agregada, modificada o eliminada.
- Puertos: asociación y desasociación.
- Wi-Fi: asignación, cambio o retiro de SSID.
- RADIUS: cambio del valor almacenado en el campo `comment` del desplegable SSID (`wifi.vlan.changed`).

La integración con Fields observa el contenedor `wifi` para capturar los valores anterior y nuevo del SSID. El cambio del valor RADIUS del desplegable se audita para los activos relacionados.

## Configuración de alertas

En el panel de Auditoría WiFi se puede activar o desactivar la auditoría por tipo de activo y seleccionar los eventos que deben notificarse. Desmarcar una notificación no elimina ni desactiva el registro histórico. Mobile permanece siempre auditado.

**Regla obligatoria de Mobile:** los eventos `asset.created` y `asset.name.changed` siempre notifican y no pueden desactivarse desde el panel. Los demás eventos de Mobile, incluidos MAC y Wi-Fi, se pueden seleccionar individualmente.

## FML Telegram

Las notificaciones se encolan mediante la API pública:

`GlpiPlugin\Telegram\NotificationService::enqueue()`

El plugin no almacena tokens ni realiza llamadas HTTP directas a Telegram. FML Telegram procesa la cola mediante su tarea cron.

Para recibir todos los eventos, configura en FML Telegram una única suscripción con **Origen** `auditoriawifi`, **Evento** `*` y el destino deseado. La selección de alertas de este plugin determina qué eventos se notifican.

Los mensajes usan HTML compatible con Telegram y muestran el usuario que realizó el cambio, el activo, el tipo y los valores anterior/nuevo en formato legible. Los detalles internos de Fields y los datos JSON se conservan en el historial GLPI, no se incluyen en el mensaje de Telegram. El fallo de notificación nunca debe interrumpir la operación de GLPI.

## Persistencia

- `glpi_plugin_auditoriawifi_events`: historial de auditoría.
- `glpi_plugin_auditoriawifi_configs`: estado por activo y selección de alertas (`alerts_json`).

Deshabilitar una alerta no elimina registros históricos. La desinstalación del plugin conserva las tablas y el historial.

## Validación pendiente

Los cambios de SSID ya fueron comprobados por el usuario en Computer y Mobile. La configuración de alertas y el envío a FML Telegram requieren prueba en el GLPI de pruebas antes de desplegar en producción.
