<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AlertManager;
use GlpiPlugin\Auditoriawifi\AssetRegistry;
use GlpiPlugin\Auditoriawifi\AuditRepository;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_alerts'])) {
    Session::checkCSRFToken();

    $submitted = is_array($_POST['alerts'] ?? null) ? $_POST['alerts'] : [];
    $auditEnabled = is_array($_POST['audit_enabled'] ?? null) ? $_POST['audit_enabled'] : [];
    foreach (AssetRegistry::getItemtypes() as $itemtype) {
        $events = $submitted[$itemtype] ?? [];
        AlertManager::saveSelectedEvents($itemtype, is_array($events) ? $events : []);
        AlertManager::saveAuditEnabled($itemtype, isset($auditEnabled[$itemtype]) && (string) $auditEnabled[$itemtype] === '1');
    }

    Session::addMessageAfterRedirect(
        __('Configuración de alertas guardada. Las alertas obligatorias de Mobile se mantienen activas.', 'auditoriawifi'),
        false,
        INFO
    );
    Html::redirect($_SERVER['PHP_SELF']);
}

Html::header(__('Auditoría WiFi', 'auditoriawifi'), $_SERVER['PHP_SELF'], 'plugins', 'auditoriawifi');

echo "<div class='container-fluid'>";
echo "<h2><i class='ti ti-shield-lock'></i> " . $escape(__('Auditoría WiFi', 'auditoriawifi')) . "</h2>";
echo "<p>" . $escape(__('Configura qué eventos se notifican por FML Telegram. El registro histórico se conserva independientemente de las notificaciones.', 'auditoriawifi')) . "</p>";

$catalog = AlertManager::getEventCatalog();
echo "<form method='post' action='" . $escape($_SERVER['PHP_SELF']) . "'>";
echo "<input type='hidden' name='_glpi_csrf_token' value='" . $escape(Session::getNewCSRFToken()) . "'>";

echo "<div class='table-responsive'><table class='tab_cadre_fixehov'>";
echo "<tr><th>" . $escape(__('Tipo de activo', 'auditoriawifi')) . "</th><th>" . $escape(__('Tipo GLPI', 'auditoriawifi')) . "</th><th>" . $escape(__('Auditoría', 'auditoriawifi')) . "</th></tr>";
foreach (AssetRegistry::getAssets() as $itemtype => $label) {
    $enabled = AuditRepository::isEnabled($itemtype);
    $mandatoryAsset = $itemtype === 'Glpi\\CustomAsset\\mobileAsset';
    echo "<tr><td>" . $escape($label) . "</td><td><code>" . $escape($itemtype) . "</code></td><td>";
    echo "<input class='form-check-input' type='checkbox' name='audit_enabled[" . $escape($itemtype) . "]' value='1'";
    echo $enabled || $mandatoryAsset ? " checked" : "";
    echo $mandatoryAsset ? " disabled" : "";
    echo ">";
    if ($mandatoryAsset) {
        echo " <span class='badge bg-info'>" . $escape(__('Obligatoria', 'auditoriawifi')) . "</span>";
    }
    echo "</td></tr>";
}
echo "</table></div>";

echo "<h3>" . $escape(__('Configuración de alertas', 'auditoriawifi')) . "</h3>";
echo "<p>" . $escape(__('Selecciona los eventos que deben generar una notificación. Los eventos no seleccionados seguirán apareciendo en el historial.', 'auditoriawifi')) . "</p>";

foreach (AssetRegistry::getAssets() as $itemtype => $label) {
    $selected = AlertManager::getSelectedEvents($itemtype);
    $mandatory = AlertManager::getMandatoryEvents($itemtype);

    echo "<section class='mb-3'>";
    echo "<h4>" . $escape($label) . " <small class='text-muted'>(" . $escape($itemtype) . ")</small></h4>";
    echo "<div class='row'>";

    foreach ($catalog as $groupName => $events) {
        echo "<div class='col-12 col-xl-4'><div class='card mb-3'><div class='card-header'><strong>" . $escape($groupName) . "</strong></div><div class='card-body'>";
        foreach ($events as $eventKey => $eventLabel) {
            $isMandatory = in_array($eventKey, $mandatory, true);
            $checked = $isMandatory || in_array($eventKey, $selected, true);
            $inputId = 'alert_' . md5($itemtype . ':' . $eventKey);
            echo "<div class='form-check mb-2'>";
            echo "<input class='form-check-input' type='checkbox' id='" . $escape($inputId) . "' name='alerts[" . $escape($itemtype) . "][]' value='" . $escape($eventKey) . "'";
            echo $checked ? " checked" : "";
            echo $isMandatory ? " disabled" : "";
            echo ">";
            echo "<label class='form-check-label' for='" . $escape($inputId) . "'>" . $escape($eventLabel);
            if ($isMandatory) {
                echo " <span class='badge bg-info'>" . $escape(__('Obligatoria', 'auditoriawifi')) . "</span>";
            }
            echo "</label></div>";
        }
        echo "</div></div></div>";
    }

    echo "</div></section>";
}

echo "<div class='d-flex justify-content-end mb-4'>";
echo "<button type='submit' class='btn btn-primary' name='save_alerts' value='1'><i class='ti ti-device-floppy'></i> " . $escape(__('Guardar configuración', 'auditoriawifi')) . "</button>";
echo "</div></form>";

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
