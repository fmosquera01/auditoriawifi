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

    public static function saveAuditEnabled(string $itemtype, bool $enabled): void
    {
        global $DB;

        if (!AssetRegistry::isSupported($itemtype)) {
            return;
        }

        // Mobile is a mandatory RADIUS asset and must remain audited.
        if ($itemtype === self::MOBILE) {
            $enabled = true;
        }

        $DB->update('glpi_plugin_auditoriawifi_configs', [
            'is_enabled' => $enabled ? 1 : 0,
        ], ['itemtype' => $itemtype]);
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
        $service = \GlpiPlugin\Telegram\NotificationService::class;
        if (!class_exists($service) || !self::shouldNotify($itemtype, $eventKey)) {
            return;
        }

        try {
            // FML Telegram API 1.1 supports Telegram-compatible HTML messages.
            if ($service::getApiVersion() !== '1.1') {
                throw new \RuntimeException('Unsupported FML Telegram API version; expected 1.1.');
            }

            $html = static fn ($value): string => htmlspecialchars(
                (string) $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $assetLabel = AssetRegistry::getAssets()[$itemtype] ?? $itemtype;
            $asset = is_array($details['asset'] ?? null) ? $details['asset'] : [];
            $assetName = trim((string) ($asset['name'] ?? ''));
            $assetTitle = $assetName !== '' ? $assetName : $assetLabel . ' #' . $itemsId;
            $actor = self::getActorName();
            $fieldLabel = self::getFieldLabel($fieldName);
            $eventLabel = self::getEventLabel($eventKey);
            $subject = '📡 Auditoría Wi-Fi | ' . $assetTitle;

            $lines = [
                '<b>📡 AUDITORÍA WI-FI</b>',
                '<i>SONGA · SEGURIDAD DE RED</i>',
                '',
                '<b>👤 Usuario:</b> ' . $html($actor),
                '<b>📌 Evento:</b> <code>' . $html($eventKey) . '</code>',
                '<b>🖥️ Activo:</b> ' . $html($assetTitle),
                '<b>🗂️ Tipo:</b> ' . $html($assetLabel) . ' (#' . $itemsId . ')',
            ];

            if ($fieldLabel !== '') {
                $lines[] = '<b>Campo:</b> ' . $html($fieldLabel);
            }

            if ($oldValue !== null || $newValue !== null) {
                $lines[] = '';
                $lines[] = '<b>🔄 ' . $html($eventLabel) . '</b>';
                $lines[] = '🟥 <b>Anterior:</b> <code>' . $html(self::formatValue($oldValue)) . '</code>';
                $lines[] = '🟩 <b>Nuevo:</b> <code>' . $html(self::formatValue($newValue)) . '</code>';
            }

            $lines[] = '';
            $lines[] = '━━━━━━━━━━━━━━━━━━';
            $lines[] = '<i>🛡️ GLPI · Auditoría Wi-Fi | Songa IT</i>';

            $result = $service::enqueue([
                'source' => 'auditoriawifi',
                'event' => $eventKey,
                'subject' => $subject,
                'message' => implode("\n", $lines),
                'parse_mode' => 'HTML',
                'deduplication_key' => 'auditoriawifi:' . $eventId,
            ]);

            // An empty subscription match is a configuration issue, not a GLPI failure.
            if (($result['queued'] ?? 0) === 0
                && ($result['skipped'] ?? 0) === 0
                && !empty($result['errors'])
            ) {
                error_log(
                    '[Auditoria WiFi] FML Telegram did not queue notification: '
                    . implode('; ', $result['errors'])
                );
            }
        } catch (\Throwable $e) {
            // Notification delivery must never interrupt GLPI operations.
            error_log('[Auditoria WiFi] FML Telegram enqueue failed: ' . $e->getMessage());
        }
    }

    private static function shouldNotify(string $itemtype, string $eventKey): bool
    {
        if (in_array($eventKey, self::getMandatoryEvents($itemtype), true)) {
            return true;
        }

        return in_array($eventKey, self::getSelectedEvents($itemtype), true);
    }

    private static function getActorName(): string
    {
        global $DB;

        try {
            $userId = class_exists('Session') ? (int) \\Session::getLoginUserID() : 0;
            if ($userId <= 0) {
                return 'Proceso automático';
            }

            $user = $DB->request([
                'SELECT' => ['name', 'firstname', 'realname'],
                'FROM' => 'glpi_users',
                'WHERE' => ['id' => $userId],
                'LIMIT' => 1,
            ])->current();

            if (!$user) {
                return 'Usuario GLPI #' . $userId;
            }

            $displayName = trim((string) ($user['firstname'] ?? '') . ' ' . (string) ($user['realname'] ?? ''));
            return $displayName !== '' ? $displayName : (string) ($user['name'] ?? 'Usuario GLPI #' . $userId);
        } catch (\\Throwable $e) {
            return 'Usuario GLPI';
        }
    }

    private static function getFieldLabel(string $fieldName): string
    {
        return match ($fieldName) {
            'ssid' => 'SSID',
            'mac' => 'Dirección MAC',
            'vlan', 'radius_value' => 'Atributo RADIUS',
            'port', 'networkport' => 'Puerto de red',
            'asset', 'name' => 'Nombre del activo',
            default => $fieldName,
        };
    }

    private static function getEventLabel(string $eventKey): string
    {
        foreach (self::getEventCatalog() as $events) {
            if (isset($events[$eventKey])) {
                return $events[$eventKey];
            }
        }

        return 'Cambio detectado';
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin asignar';
        }

        if (is_array($value) || is_object($value)) {
            $data = (array) $value;
            foreach (['completename', 'name', 'mac', 'value'] as $key) {
                if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                    $value = $data[$key];
                    break;
                }
            }

            if (is_array($value) || is_object($value)) {
                if (isset($data['itemtype'], $data['items_id'])) {
                    $type = AssetRegistry::getAssets()[(string) $data['itemtype']] ?? (string) $data['itemtype'];
                    $value = $type . ' #' . (int) $data['items_id'];
                } else {
                    $value = 'Cambio registrado';
                }
            }
        }

        $text = trim((string) $value);
        if (strlen($text) > 300) {
            $text = substr($text, 0, 297) . '...';
        }

        return $text;
    }
}
