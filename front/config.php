<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AssetRegistry;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

Html::header(__('Auditoría WiFi', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins', 'auditoriawifi');

echo "<div class='container-fluid'>";
echo "<h2><i class='ti ti-shield-lock'></i> " . $escape(__('Auditoría WiFi', 'auditoriawifi')) . "</h2>";
echo "<p>" . $escape(__('Panel inicial de auditoría de activos y autorizaciones inalámbricas.', 'auditoriawifi')) . "</p>";

echo "<div class='alert alert-info'>";
echo $escape(__('Esta primera versión instala el registro de eventos y activa la captura para los cuatro tipos de activos definidos. La configuración individual de alertas y la integración con FML Telegram se incorporarán en la siguiente etapa.', 'auditoriawifi'));
echo "</div>";

echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
echo "<tr><th>" . $escape(__('Tipo de activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Tipo GLPI', 'auditoriawifi')) . "</th><th>" . $escape(__('Auditoría', 'auditoriawifi')) . "</th></tr>";
foreach (AssetRegistry::getAssets() as $itemtype => $label) {
    $enabled = \GlpiPlugin\Auditoriawifi\AuditRepository::isEnabled($itemtype);
    echo "<tr><td>" . $escape($label) . "</td><td><code>" . $escape($itemtype) . "</code></td><td>";
    echo $enabled
        ? "<span class='badge bg-success'>" . $escape(__('Activa', 'auditoriawifi')) . "</span>"
        : "<span class='badge bg-secondary'>" . $escape(__('Inactiva', 'auditoriawifi')) . "</span>";
    echo "</td></tr>";
}
echo "</table></div>";

echo "<h3>" . $escape(__('Últimos eventos registrados', 'auditoriawifi')) . "</h3>";
global $DB;
if ($DB->tableExists('glpi_plugin_auditoriawifi_events')) {
    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_auditoriawifi_events',
        'ORDER' => ['date_creation DESC', 'id DESC'],
        'LIMIT' => 50,
    ]);
    echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
    echo "<tr><th>ID</th><th>" . $escape(__('Fecha', 'auditoriawifi')) . "</th><th>" . $escape(__('Evento', 'auditoriawifi')) . "</th><th>" . $escape(__('Activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Campo', 'auditoriawifi')) . "</th><th>" . $escape(__('Anterior', 'auditoriawifi')) . "</th><th>" . $escape(__('Nuevo', 'auditoriawifi')) . "</th></tr>";
    foreach ($iterator as $event) {
        echo "<tr>";
        echo "<td>" . (int) $event['id'] . "</td>";
        echo "<td>" . $escape((string) $event['date_creation']) . "</td>";
        echo "<td><code>" . $escape((string) $event['event_key']) . "</code></td>";
        echo "<td>" . $escape((string) $event['itemtype']) . " #" . (int) $event['items_id'] . "</td>";
        echo "<td>" . $escape((string) $event['field_name']) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['old_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "<td>" . $escape(mb_strimwidth((string) ($event['new_value'] ?? ''), 0, 100, '…')) . "</td>";
        echo "</tr>";
    }
    echo "</table></div>";
}

echo "</div>";
Html::footer();
