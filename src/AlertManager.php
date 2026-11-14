<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

final class AlertManager
{
    private const MOBILE = 'Glpi\CustomAsset\mobileAsset';

    /** @return array<string, array<string, string>> */
    public static function getEventCatalog(): array
    {
        return [
            'Activo' => [
                'asset.created' => 'Activo creado',
                'asset.name.changed' => 'Cambio de nombre',
                'asset.deleted' => 'Activo eliminado',
                'asset.purged' => 'Activo purgado',
            ],
            'MAC y puertos' => [
                'wifi.mac.added' => 'MAC agregada',
                'wifi.mac.changed' => 'MAC modificada',
                'wifi.mac.removed' => 'MAC eliminada',
                'wifi.port.associated' => 'Puerto asociado',
                'wifi.port.disassociated' => 'Puerto desasociado',
            ],
            'Wi-Fi y RADIUS' => [
                'wifi.ssid.added' => 'SSID asignado',
                'wifi.ssid.changed' => 'Cambio de SSID',
                'wifi.ssid.removed' => 'SSID retirado',
                'wifi.vlan.changed' => 'Cambio de atributo RADIUS',
            ],
        ];
    }

    /** @return string[] */
    public static function getMandatoryEvents(string $itemtype): array
    {
        return $itemtype === self::MOBILE
            ? ['asset.created', 'asset.name.changed']
            : [];
    }

    /** @return string[] */
    public static function getSelectedEvents(string $itemtype): array
    {
        global $DB;

        $row = $DB->request([
            'SELECT' => ['alerts_json'],
            'FROM' => 'glpi_plugin_auditoriawifi_configs',
            'WHERE' => ['itemtype' => $itemtype],
            'LIMIT' => 1,
        ])->current();

        $decoded = json_decode((string) ($row['alerts_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        // Accept both the current associative format and a legacy list.
        $selected = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && $value === true) {
                $selected[] = $key;
            } elseif (is_int($key) && is_string($value)) {
                $selected[] = $value;
            }
        }

        return array_values(array_unique($selected));
    }

    /** @param string[] $events */
    public static function saveSelectedEvents(string $itemtype, array $events): void
    {
        global $DB;

        if (!AssetRegistry::isSupported($itemtype)) {
            return;
        }

        $allowed = [];
        foreach (self::getEventCatalog() as $group) {
            $allowed = array_merge($allowed, array_keys($group));
        }

        $events = array_values(array_unique(array_filter(
            $events,
            static fn ($event): bool => is_string($event) && in_array($event, $allowed, true)
        )));

        // Mandatory Mobile events are always included and cannot be disabled.
        $events = array_values(array_unique(array_merge(
            $events,
            self::getMandatoryEvents($itemtype)
        )));

        $json = json_encode(array_fill_keys($events, true), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_plugin_auditoriawifi_configs',
            'WHERE' => ['itemtype' => $itemtype],
            'LIMIT' => 1,
        ])->current();

        if ($existing) {
            $DB->update('glpi_plugin_auditoriawifi_configs', [
                'alerts_json' => $json,
            ], ['id' => (int) $existing['id']]);
        } else {
            $DB->insert('glpi_plugin_auditoriawifi_configs', [
                'itemtype' => $itemtype,
                'is_enabled' => 1,
                'alerts_json' => $json,
            ]);
        }
    }

    public static function dispatch(
        int $eventId,
        string $eventKey,
        string $itemtype,
        int $itemsId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        array $details = []
    ): void {
        if (
            !class_exists(\GlpiPlugin\Telegram\NotificationService::class)
            || !self::shouldNotify($itemtype, $eventKey)
        ) {
            return;
        }

        $assetLabel = AssetRegistry::getAssets()[$itemtype] ?? $itemtype;
        $subject = '📡 Auditoría Wi-Fi: ' . $assetLabel . ' #' . $itemsId;
        $lines = [
            '🔐 SONGA | AUDITORÍA WI-FI',
            '📌 Evento: ' . $eventKey,
            '🖥️ Activo: ' . $assetLabel . ' #' . $itemsId,
        ];

        if ($fieldName !== '') {
            $lines[] = '🏷️ Campo: ' . $fieldName;
        }
        if ($oldValue !== null || $newValue !== null) {
            $lines[] = '⬅️ Anterior: ' . self::formatValue($oldValue);
            $lines[] = '➡️ Nuevo: ' . self::formatValue($newValue);
        }
        if ($details !== []) {
            $lines[] = 'ℹ️ Detalle: ' . self::formatValue($details);
        }
        $lines[] = '🆔 <b>Registro:</b> #' . $eventId;

        try {
            \GlpiPlugin\Telegram\NotificationService::enqueue([
                'source' => 'auditoriawifi',
                'event' => $eventKey,
                'subject' => $subject,
                'message' => implode("\n", $lines),
                'deduplication_key' => 'auditoriawifi:' . $eventId,
            ]);
        } catch (\Throwable $e) {
            // Notification delivery must never interrupt GLPI operations.
            if (class_exists('Toolbox')) {
                \Toolbox::logError('Auditoria WiFi: FML Telegram enqueue failed', $e->getMessage());
            }
        }
    }

    private static function shouldNotify(string $itemtype, string $eventKey): bool
    {
        if (in_array($eventKey, self::getMandatoryEvents($itemtype), true)) {
            return true;
        }

        return in_array($eventKey, self::getSelectedEvents($itemtype), true);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(vacío)';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $text = trim((string) $value);
        if (strlen($text) > 1200) {
            $text = substr($text, 0, 1197) . '...';
        }

        return $text;
    }
}
