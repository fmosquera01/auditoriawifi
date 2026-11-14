<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AssetRegistry;
use GlpiPlugin\Auditoriawifi\ConfigPage;

include('../../../inc/includes.php');

Session::checkRight('config', READ);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

Html::header(__('Eventos', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins');

echo "<div class='container-fluid'>";
echo "<nav aria-label='breadcrumb' class='mb-3'><ol class='breadcrumb'><li class='breadcrumb-item'><a href='/front/central.php'>" . $escape(__('Inicio')) . "</a></li><li class='breadcrumb-item'><a href='/plugins/auditoriawifi/front/home.php'>Auditoría WiFi</a></li><li class='breadcrumb-item active' aria-current='page'>" . $escape(__('Eventos', 'auditoriawifi')) . "</li></ol></nav>";
echo "<div class='d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3'><h2 class='mb-0'><i class='ti ti-bell'></i> " . $escape(__('Visualizar alertas', 'auditoriawifi')) . "</h2><div class='d-flex gap-2'><a class='btn btn-outline-secondary' href='/plugins/auditoriawifi/front/home.php'><i class='ti ti-arrow-left'></i> " . $escape(__('Inicio', 'auditoriawifi')) . "</a><a class='btn btn-primary' href='/plugins/auditoriawifi/front/config.php'><i class='ti ti-settings'></i> " . $escape(__('Configuración', 'auditoriawifi')) . "</a></div></div>";
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
    echo "<tr><th>ID</th><th>" . $escape(__('Fecha', 'auditoriawifi')) . "</th><th>" . $escape(__('Usuario', 'auditoriawifi')) . "</th><th>" . $escape(__('Evento', 'auditoriawifi')) . "</th><th>" . $escape(__('Activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Campo', 'auditoriawifi')) . "</th><th>" . $escape(__('Anterior', 'auditoriawifi')) . "</th><th>" . $escape(__('Nuevo', 'auditoriawifi')) . "</th></tr>";

    $count = 0;
    foreach ($iterator as $event) {
        $count++;
        $itemtype = (string) $event['itemtype'];
        $assetLabel = $assets[$itemtype] ?? $itemtype;
        echo "<tr>";
        echo "<td>" . (int) $event['id'] . "</td>";
        echo "<td>" . $escape((string) $event['date_creation']) . "</td>";
        $userId = (int) ($event['users_id'] ?? 0);
        $userName = $userId > 0 ? getUserName($userId) : __('No identificado', 'auditoriawifi');
        echo "<td>" . $escape($userName) . ($userId > 0 ? " <small class='text-muted'>#" . $userId . "</small>" : '') . "</td>";
        echo "<td><code>" . $escape((string) $event['event_key']) . "</code></td>";
        echo "<td>" . $escape($assetLabel) . " #" . (int) $event['items_id'] . "</td>";
        echo "<td>" . $escape((string) $event['field_name']) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['old_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['new_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "</tr>";
    }

    if ($count === 0) {
        echo "<tr><td colspan='8'>" . $escape(__('Todavía no hay eventos registrados.', 'auditoriawifi')) . "</td></tr>";
    }

    echo "</table></div>";
    echo "<p class='text-muted'>" . $escape(sprintf(__('Se muestran los últimos %d eventos como máximo.', 'auditoriawifi'), 200)) . "</p>";
}

echo "</div>";
Html::footer();
