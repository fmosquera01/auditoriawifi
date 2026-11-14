<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use Throwable;

final class AuditRepository
{
    public static function isEnabled(string $itemtype): bool
    {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_auditoriawifi_configs')) {
            return false;
        }

        $row = $DB->request([
            'SELECT' => ['is_enabled'],
            'FROM' => 'glpi_plugin_auditoriawifi_configs',
            'WHERE' => ['itemtype' => $itemtype],
            'LIMIT' => 1,
        ])->current();

        return $row !== null && (int) $row['is_enabled'] === 1;
    }

    public static function record(
        string $eventKey,
        string $itemtype,
        int $itemsId,
        int $entitiesId,
        string $eventGroup,
        string $fieldName = '',
        mixed $oldValue = null,
        mixed $newValue = null,
        array $details = []
    ): void {
        global $DB;

        if (!$DB->tableExists('glpi_plugin_auditoriawifi_events')) {
            return;
        }

        try {
            $userId = class_exists('Session') ? (int) \Session::getLoginUserID() : 0;
            $eventId = (int) $DB->insert('glpi_plugin_auditoriawifi_events', [
                'event_key' => $eventKey,
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
                'entities_id' => $entitiesId,
                'event_group' => $eventGroup,
                'field_name' => $fieldName,
                'old_value' => self::encodeValue($oldValue),
                'new_value' => self::encodeValue($newValue),
                'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'users_id' => $userId,
            ]);

            if ($eventId > 0) {
                AlertManager::dispatch(
                    $eventId,
                    $eventKey,
                    $itemtype,
                    $itemsId,
                    $fieldName,
                    $oldValue,
                    $newValue,
                    $details
                );
            }
        } catch (Throwable $e) {
            // Auditing must never block the GLPI transaction.
            if (class_exists('Toolbox')) {
                \Toolbox::logError('Auditoria WiFi: unable to persist audit event', $e->getMessage());
            }
        }
    }

    private static function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
