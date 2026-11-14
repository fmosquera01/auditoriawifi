<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AssetRegistry;

include('../../../inc/includes.php');

Session::checkRight('config', READ);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

Html::header(__('Visualizar alertas', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins', 'auditoriawifi');

echo "<div class='container-fluid'>";
echo "<h2><i class='ti ti-bell'></i> " . $escape(__('Visualizar alertas', 'auditoriawifi')) . "</h2>";
echo "<p>" . $escape(__('Historial de eventos detectados por Auditoría WiFi. La selección de qué eventos generan notificaciones se modifica en Parámetros.', 'auditoriawifi')) . "</p>";

global $DB;
if (!$DB->tableExists('glpi_plugin_auditoriawifi_events')) {
    echo "<div class='alert alert-warning'>" . $escape(__('La tabla de auditoría todavía no está disponible.', 'auditoriawifi')) . "</div>";
} else {
    $assets = AssetRegistry::getAssets();
    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_auditoriawifi_events',
        'ORDER' => ['date_creation DESC', 'id DESC'],
        'LIMIT' => 200,
    ]);

    echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
    echo "<tr><th>ID</th><th>" . $escape(__('Fecha', 'auditoriawifi')) . "</th><th>" . $escape(__('Evento', 'auditoriawifi')) . "</th><th>" . $escape(__('Activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Campo', 'auditoriawifi')) . "</th><th>" . $escape(__('Anterior', 'auditoriawifi')) . "</th><th>" . $escape(__('Nuevo', 'auditoriawifi')) . "</th></tr>";

    $count = 0;
    foreach ($iterator as $event) {
        $count++;
        $itemtype = (string) $event['itemtype'];
        $assetLabel = $assets[$itemtype] ?? $itemtype;
        echo "<tr>";
        echo "<td>" . (int) $event['id'] . "</td>";
        echo "<td>" . $escape((string) $event['date_creation']) . "</td>";
        echo "<td><code>" . $escape((string) $event['event_key']) . "</code></td>";
        echo "<td>" . $escape($assetLabel) . " #" . (int) $event['items_id'] . "</td>";
        echo "<td>" . $escape((string) $event['field_name']) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['old_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['new_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "</tr>";
    }

    if ($count === 0) {
        echo "<tr><td colspan='7'>" . $escape(__('Todavía no hay eventos registrados.', 'auditoriawifi')) . "</td></tr>";
    }

    echo "</table></div>";
    echo "<p class='text-muted'>" . $escape(sprintf(__('Se muestran los últimos %d eventos como máximo.', 'auditoriawifi'), 200)) . "</p>";
}

echo "</div>";
Html::footer();
