<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AssetRegistry;

include('../../../inc/includes.php');

Session::checkRight('auditoriawifi:events', READ);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/**
 * Render only the event list. This same fragment is returned to the refresh
 * request so the browser can update the table without reloading GLPI.
 */
$renderEvents = static function () use ($escape): void {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_auditoriawifi_events')) {
        echo "<div class='alert alert-warning'>" . $escape(__('La tabla de auditoría todavía no está disponible.', 'auditoriawifi')) . "</div>";
        return;
    }

    $assets = AssetRegistry::getAssets();
    $assetCache = [];
    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_auditoriawifi_events',
        'ORDER' => ['date_creation DESC', 'id DESC'],
        'LIMIT' => 200,
    ]);

    echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
    echo "<tr><th>ID</th><th>" . $escape(__('Fecha', 'auditoriawifi')) . "</th><th>" . $escape(__('Usuario', 'auditoriawifi')) . "</th><th>" . $escape(__('Evento', 'auditoriawifi')) . "</th><th>" . $escape(__('Activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Número de serie', 'auditoriawifi')) . "</th><th>" . $escape(__('Campo', 'auditoriawifi')) . "</th><th>" . $escape(__('Anterior', 'auditoriawifi')) . "</th><th>" . $escape(__('Nuevo', 'auditoriawifi')) . "</th></tr>";

    $count = 0;
    foreach ($iterator as $event) {
        $count++;
        $itemtype = (string) $event['itemtype'];
        $itemsId = (int) $event['items_id'];
        $assetLabel = $assets[$itemtype] ?? $itemtype;

        $details = json_decode((string) ($event['details_json'] ?? ''), true);
        $assetDetails = is_array($details) && is_array($details['asset'] ?? null)
            ? $details['asset']
            : [];

        $assetName = trim((string) ($assetDetails['name'] ?? ''));
        $serial = trim((string) ($assetDetails['serial'] ?? ''));
        $inventory = trim((string) ($assetDetails['otherserial'] ?? ''));

        // Older events do not have a snapshot; resolve the current asset once per item.
        if (($assetName === '' || $serial === '' || $inventory === '') && $itemsId > 0) {
            $cacheKey = $itemtype . ':' . $itemsId;
            if (!array_key_exists($cacheKey, $assetCache)) {
                $assetCache[$cacheKey] = [];
                if (isset($assets[$itemtype])) {
                    try {
                        $assetItem = getItemForItemtype($itemtype);
                        if ($assetItem && $assetItem->getFromDB($itemsId)) {
                            $assetCache[$cacheKey] = [
                                'name' => (string) ($assetItem->fields['name'] ?? ''),
                                'serial' => (string) ($assetItem->fields['serial'] ?? ''),
                                'otherserial' => (string) ($assetItem->fields['otherserial'] ?? ''),
                            ];
                        }
                    } catch (Throwable $e) {
                        // A missing or purged asset must not prevent history display.
                    }
                }
            }
            $current = $assetCache[$cacheKey];
            $assetName = $assetName !== '' ? $assetName : trim((string) ($current['name'] ?? ''));
            $serial = $serial !== '' ? $serial : trim((string) ($current['serial'] ?? ''));
            $inventory = $inventory !== '' ? $inventory : trim((string) ($current['otherserial'] ?? ''));
        }

        echo "<tr>";
        echo "<td>" . (int) $event['id'] . "</td>";
        echo "<td>" . $escape((string) $event['date_creation']) . "</td>";
        $userId = (int) ($event['users_id'] ?? 0);
        $userName = $userId > 0 ? getUserName($userId) : __('No identificado', 'auditoriawifi');
        echo "<td>" . $escape($userName) . ($userId > 0 ? " <small class='text-muted'>#" . $userId . "</small>" : '') . "</td>";
        echo "<td><code>" . $escape((string) $event['event_key']) . "</code></td>";
        echo "<td><strong>" . $escape($assetName !== '' ? $assetName : __('(sin nombre)', 'auditoriawifi')) . "</strong><br><small class='text-muted'>" . $escape($assetLabel) . " #" . $itemsId . ($inventory !== '' ? " · AF: " . $escape($inventory) : '') . "</small></td>";
        echo "<td>" . $escape($serial !== '' ? $serial : '—') . "</td>";
        echo "<td>" . $escape((string) $event['field_name']) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['old_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['new_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "</tr>";
    }

    if ($count === 0) {
        echo "<tr><td colspan='9'>" . $escape(__('Todavía no hay eventos registrados.', 'auditoriawifi')) . "</td></tr>";
    }

    echo "</table></div>";
    echo "<p class='text-muted'>" . $escape(sprintf(__('Se muestran los últimos %d eventos como máximo.', 'auditoriawifi'), 200)) . "</p>";
};

// AJAX response: return only the table fragment, not the full GLPI page.
if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    $renderEvents();
    exit;
}

Html::header(__('Eventos', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins');

echo "<div class='container-fluid'>";
echo "<nav aria-label='breadcrumb' class='mb-3'><ol class='breadcrumb'><li class='breadcrumb-item'><a href='/front/central.php'>" . $escape(__('Inicio')) . "</a></li><li class='breadcrumb-item'>Complementos</li><li class='breadcrumb-item'><a href='/plugins/auditoriawifi/front/home.php'>Auditoría WiFi</a></li><li class='breadcrumb-item active' aria-current='page'>" . $escape(__('Eventos', 'auditoriawifi')) . "</li></ol></nav>";
echo "<div class='d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3'><h2 class='mb-0'><i class='ti ti-bell'></i> " . $escape(__('Visualizar alertas', 'auditoriawifi')) . "</h2><div class='d-flex gap-2'><button type='button' class='btn btn-outline-primary' id='refresh-events'><i class='ti ti-refresh'></i> " . $escape(__('Refrescar eventos', 'auditoriawifi')) . "</button><a class='btn btn-outline-secondary' href='/plugins/auditoriawifi/front/home.php'><i class='ti ti-arrow-left'></i> " . $escape(__('Volver al inicio', 'auditoriawifi')) . "</a><a class='btn btn-primary' href='/plugins/auditoriawifi/front/config.php'><i class='ti ti-settings'></i> " . $escape(__('Volver a configuración', 'auditoriawifi')) . "</a></div></div>";
echo "<p>" . $escape(__('Historial de eventos detectados por Auditoría WiFi. La selección de qué eventos generan notificaciones se modifica en Parámetros.', 'auditoriawifi')) . "</p>";
echo "<div class='d-flex justify-content-end mb-2'><small class='text-muted' id='events-refresh-status'></small></div>";
echo "<div id='events-container'>";
$renderEvents();
echo "</div>";

echo "<script>
(() => {
    const button = document.getElementById('refresh-events');
    const container = document.getElementById('events-container');
    const status = document.getElementById('events-refresh-status');
    if (!button || !container) return;

    button.addEventListener('click', async () => {
        if (button.disabled) return;
        button.disabled = true;
        const original = button.innerHTML;
        button.innerHTML = '<i class="ti ti-loader-2"></i> Actualizando...';
        try {
            const response = await fetch('/plugins/auditoriawifi/front/alerts.php?ajax=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            container.innerHTML = await response.text();
            if (status) status.textContent = 'Actualizado: ' + new Date().toLocaleTimeString();
        } catch (error) {
            if (status) status.textContent = 'No se pudo actualizar. Intenta nuevamente.';
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
})();
</script>";

echo "</div>";
Html::footer();
