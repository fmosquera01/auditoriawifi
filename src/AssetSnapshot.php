<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

final class AssetSnapshot
{
    public static function capture(string $itemtype, int $itemsId): array
    {
        global $DB;

        if (!AssetRegistry::isSupported($itemtype) || $itemsId <= 0) {
            return [];
        }

        $item = getItemForItemtype($itemtype);
        if (!$item || !$item->getFromDB($itemsId)) {
            return [];
        }

        $fields = $item->fields;
        $asset = [
            'id' => (int) ($fields['id'] ?? 0),
            'name' => (string) ($fields['name'] ?? ''),
            'serial' => (string) ($fields['serial'] ?? ''),
            'otherserial' => (string) ($fields['otherserial'] ?? ''),
            'entities_id' => (int) ($fields['entities_id'] ?? 0),
            'is_deleted' => (int) ($fields['is_deleted'] ?? 0),
        ];

        $ports = [];
        $iterator = $DB->request([
            'SELECT' => ['id', 'items_id', 'itemtype', 'name', 'logical_number', 'mac', 'is_deleted'],
            'FROM' => 'glpi_networkports',
            'WHERE' => [
                'itemtype' => $itemtype,
                'items_id' => $itemsId,
                'is_deleted' => 0,
            ],
            'ORDER' => ['id'],
        ]);
        foreach ($iterator as $row) {
            $ports[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'logical_number' => (int) ($row['logical_number'] ?? 0),
                'mac' => self::normalizeMac((string) ($row['mac'] ?? '')),
            ];
        }

        return [
            'asset' => $asset,
            'ports' => $ports,
            'wifi' => self::captureWifi($itemtype, $itemsId),
        ];
    }

    public static function capturePort(int $portId): array
    {
        global $DB;

        $row = $DB->request([
            'FROM' => 'glpi_networkports',
            'WHERE' => ['id' => $portId],
            'LIMIT' => 1,
        ])->current();

        if (!$row || !AssetRegistry::isSupported((string) $row['itemtype'])) {
            return [];
        }

        return [
            'id' => (int) $row['id'],
            'items_id' => (int) $row['items_id'],
            'itemtype' => (string) $row['itemtype'],
            'name' => (string) ($row['name'] ?? ''),
            'logical_number' => (int) ($row['logical_number'] ?? 0),
            'mac' => self::normalizeMac((string) ($row['mac'] ?? '')),
            'is_deleted' => (int) ($row['is_deleted'] ?? 0),
        ];
    }

    public static function captureWifi(string $itemtype, int $itemsId): array
    {
        global $DB;

        $tables = [
            'Computer' => 'glpi_plugin_fields_computerwifis',
            'Printer' => 'glpi_plugin_fields_printerwifis',
            'Phone' => 'glpi_plugin_fields_phonewifis',
            'Glpi\\\\CustomAsset\\\\mobileAsset' => 'glpi_plugin_fields_glpicustomassetmobileassetwifis',
        ];
        if (!isset($tables[$itemtype]) || !$DB->tableExists($tables[$itemtype])) {
            return [];
        }

        $criteria = ['items_id' => $itemsId];
        if ($DB->fieldExists($tables[$itemtype], 'itemtype')) {
            $criteria['itemtype'] = $itemtype;
        }

        $row = $DB->request([
            'FROM' => $tables[$itemtype],
            'WHERE' => $criteria,
            'LIMIT' => 1,
        ])->current();

        if (!$row) {
            return ['dropdown_id' => 0, 'ssid_name' => '', 'ssid_completename' => '', 'radius_value' => ''];
        }

        $dropdownId = (int) ($row['plugin_fields_ssidfielddropdowns_id'] ?? 0);
        $ssid = [];
        if ($dropdownId > 0 && $DB->tableExists('glpi_plugin_fields_ssidfielddropdowns')) {
            $ssid = $DB->request([
                'SELECT' => ['id', 'name', 'completename', 'comment'],
                'FROM' => 'glpi_plugin_fields_ssidfielddropdowns',
                'WHERE' => ['id' => $dropdownId],
                'LIMIT' => 1,
            ])->current() ?? [];
        }

        return [
            'dropdown_id' => $dropdownId,
            'ssid_name' => (string) ($ssid['name'] ?? ''),
            'ssid_completename' => (string) ($ssid['completename'] ?? ''),
            'radius_value' => (string) ($ssid['comment'] ?? ''),
        ];
    }

    public static function normalizeMac(string $mac): string
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        return preg_replace('/[^0-9A-F:]/', '', $mac) ?? '';
    }
}
