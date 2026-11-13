<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AssetRegistry;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header(__('Auditoría WiFi', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins', 'auditoriawifi');

echo "<div class='container-fluid'>";
echo "<h2><i class='ti ti-shield-lock'></i> " . h(__('Auditoría WiFi', 'auditoriawifi')) . "</h2>";
echo "<p>" . h(__('Panel inicial de auditoría de activos y autorizaciones inalámbricas.', 'auditoriawifi')) . "</p>";

echo "<div class='alert alert-info'>";
echo h(__('Esta primera versión instala el registro de eventos y activa la captura para los cuatro tipos de activos definidos. La configuración individual de alertas y la integración con FML Telegram se incorporarán en la siguiente etapa.', 'auditoriawifi'));
echo "</div>";

echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
echo "<tr><th>" . h(__('Tipo de activo', 'auditoriawifi')) . "</th><th>" . h(__('Tipo GLPI', 'auditoriawifi')) . "</th><th>" . h(__('Auditoría', 'auditoriawifi')) . "</th></tr>";
foreach (AssetRegistry::getAssets() as $itemtype => $label) {
    $enabled = \GlpiPlugin\Auditoriawifi\AuditRepository::isEnabled($itemtype);
    echo "<tr><td>" . h($label) . "</td><td><code>" . h($itemtype) . "</code></td><td>";
    echo $enabled
        ? "<span class='badge bg-success'>" . h(__('Activa', 'auditoriawifi')) . "</span>"
        : "<span class='badge bg-secondary'>" . h(__('Inactiva', 'auditoriawifi')) . "</span>";
    echo "</td></tr>";
}
echo "</table></div>";

echo "<h3>" . h(__('Últimos eventos registrados', 'auditoriawifi')) . "</h3>";
global $DB;
if ($DB->tableExists('glpi_plugin_auditoriawifi_events')) {
    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_auditoriawifi_events',
        'ORDER' => ['date_creation DESC', 'id DESC'],
        'LIMIT' => 50,
    ]);
    echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
    echo "<tr><th>ID</th><th>" . h(__('Fecha', 'auditoriawifi')) . "</th><th>" . h(__('Evento', 'auditoriawifi')) . "</th><th>" . h(__('Activo', 'auditoriawifi')) . "</th><th>" . h(__('Campo', 'auditoriawifi')) . "</th><th>" . h(__('Anterior', 'auditoriawifi')) . "</th><th>" . h(__('Nuevo', 'auditoriawifi')) . "</th></tr>";
    foreach ($iterator as $event) {
        echo "<tr>";
        echo "<td>" . (int) $event['id'] . "</td>";
        echo "<td>" . h((string) $event['date_creation']) . "</td>";
        echo "<td><code>" . h((string) $event['event_key']) . "</code></td>";
        echo "<td>" . h((string) $event['itemtype']) . " #" . (int) $event['items_id'] . "</td>";
        echo "<td>" . h((string) $event['field_name']) . "</td>";
        echo "<td>" . h(mb_strimwidth((string) ($event['old_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "<td>" . h(mb_strimwidth((string) ($event['new_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "</tr>";
    }
    echo "</table></div>";
}

echo "</div>";
Html::footer();
