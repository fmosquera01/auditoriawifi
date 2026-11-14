<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonDBTM;

final class EventObserver
{
    /** @var array<string, array<string, mixed>> */
    private static array $before = [];

    public static function preAssetAdd(CommonDBTM $item): void
    {
        // Reserved for capturing submitted fields before persistence.
    }

    public static function postAssetAdd(CommonDBTM $item): void
    {
        $itemtype = $item::class;
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }

        AuditRepository::record(
            'asset.created',
            $itemtype,
            (int) $item->getID(),
            (int) ($item->fields['entities_id'] ?? 0),
            'asset',
            'asset',
            null,
            ['name' => $item->fields['name'] ?? '', 'serial' => $item->fields['serial'] ?? '']
        );

        self::recordSnapshotDifferences($itemtype, (int) $item->getID(), []);
    }

    public static function preAssetUpdate(CommonDBTM $item): void
    {
        $itemtype = $item::class;
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }
        self::$before[self::key($itemtype, (int) $item->getID())] = AssetSnapshot::capture($itemtype, (int) $item->getID());
    }

    public static function postAssetUpdate(CommonDBTM $item): void
    {
        $itemtype = $item::class;
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }
        $key = self::key($itemtype, (int) $item->getID());
        $before = self::$before[$key] ?? [];
        unset(self::$before[$key]);
        self::recordSnapshotDifferences($itemtype, (int) $item->getID(), $before, false);
    }

    public static function preAssetDelete(CommonDBTM $item): void
    {
        self::recordAssetRemoval($item, 'asset.deleted');
    }

    public static function preAssetPurge(CommonDBTM $item): void
    {
        self::recordAssetRemoval($item, 'asset.purged');
    }

    public static function preNetworkPortAdd(CommonDBTM $item): void
    {
        self::$before[self::key('NetworkPort', (int) $item->getID())] = [];
    }

    public static function postNetworkPortAdd(CommonDBTM $item): void
    {
        self::recordPortChange($item, []);
    }

    public static function preNetworkPortUpdate(CommonDBTM $item): void
    {
        self::$before[self::key('NetworkPort', (int) $item->getID())] = AssetSnapshot::capturePort((int) $item->getID());
    }

    public static function postNetworkPortUpdate(CommonDBTM $item): void
    {
        $key = self::key('NetworkPort', (int) $item->getID());
        $before = self::$before[$key] ?? [];
        unset(self::$before[$key]);
        self::recordPortChange($item, $before);
    }

    public static function preNetworkPortDelete(CommonDBTM $item): void
    {
        self::recordPortRemoval($item, 'wifi.mac.removed');
    }

    public static function preNetworkPortPurge(CommonDBTM $item): void
    {
        self::recordPortRemoval($item, 'wifi.mac.removed');
    }

    private static function recordAssetRemoval(CommonDBTM $item, string $event): void
    {
        $itemtype = $item::class;
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }
        AuditRepository::record($event, $itemtype, (int) $item->getID(), (int) ($item->fields['entities_id'] ?? 0), 'asset');
    }

    private static function recordSnapshotDifferences(string $itemtype, int $itemsId, array $before, bool $includePorts = true): void
    {
        $after = AssetSnapshot::capture($itemtype, $itemsId);
        if ($after === []) {
            return;
        }

        $oldAsset = $before['asset'] ?? [];
        $newAsset = $after['asset'] ?? [];
        if (isset($oldAsset['name'], $newAsset['name']) && $oldAsset['name'] !== $newAsset['name']) {
            AuditRepository::record('asset.name.changed', $itemtype, $itemsId, (int) ($newAsset['entities_id'] ?? 0), 'asset', 'name', $oldAsset['name'], $newAsset['name']);
        }

        if ($includePorts) {
            $oldPorts = $before['ports'] ?? [];
            $newPorts = $after['ports'] ?? [];
            foreach (array_unique(array_merge(array_keys($oldPorts), array_keys($newPorts))) as $portId) {
                $old = $oldPorts[$portId] ?? null;
                $new = $newPorts[$portId] ?? null;
                $oldMac = (string) ($old['mac'] ?? '');
                $newMac = (string) ($new['mac'] ?? '');
                if ($oldMac !== $newMac) {
                    $event = $oldMac === '' ? 'wifi.mac.added' : ($newMac === '' ? 'wifi.mac.removed' : 'wifi.mac.changed');
                    AuditRepository::record($event, $itemtype, $itemsId, (int) ($newAsset['entities_id'] ?? 0), 'mac', 'mac', $oldMac, $newMac, ['networkports_id' => (int) $portId]);
                }
            }
    
        }

        $oldWifi = $before['wifi'] ?? [];
        $newWifi = $after['wifi'] ?? [];
        $oldId = (int) ($oldWifi['dropdown_id'] ?? 0);
        $newId = (int) ($newWifi['dropdown_id'] ?? 0);
        $oldRadius = (string) ($oldWifi['radius_value'] ?? '');
        $newRadius = (string) ($newWifi['radius_value'] ?? '');
        if ($oldId !== $newId || $oldRadius !== $newRadius) {
            $event = $oldId === 0 ? 'wifi.ssid.added' : ($newId === 0 ? 'wifi.ssid.removed' : 'wifi.ssid.changed');
            AuditRepository::record($event, $itemtype, $itemsId, (int) ($newAsset['entities_id'] ?? 0), 'ssid', 'ssid', $oldWifi, $newWifi);
        }
    }

    private static function recordPortChange(CommonDBTM $port, array $before): void
    {
        $after = AssetSnapshot::capturePort((int) $port->getID());
        if ($after === []) {
            return;
        }
        $itemtype = (string) $after['itemtype'];
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }

        $oldItemtype = (string) ($before['itemtype'] ?? '');
        $oldItemsId = (int) ($before['items_id'] ?? 0);
        $newItemsId = (int) $after['items_id'];
        $entityId = (int) ((AssetSnapshot::capture($itemtype, $newItemsId)['asset']['entities_id'] ?? 0));

        if ($oldItemsId === 0 || $oldItemtype === '' || $oldItemtype !== $itemtype || $oldItemsId !== $newItemsId) {
            if (AssetRegistry::isSupported($oldItemtype) && $oldItemsId > 0 && AuditRepository::isEnabled($oldItemtype)) {
                AuditRepository::record(
                    'wifi.port.disassociated',
                    $oldItemtype,
                    $oldItemsId,
                    0,
                    'port',
                    'networkport',
                    ['itemtype' => $oldItemtype, 'items_id' => $oldItemsId],
                    null,
                    ['networkports_id' => (int) $after['id']]
                );
            }
            AuditRepository::record(
                'wifi.port.associated',
                $itemtype,
                $newItemsId,
                $entityId,
                'port',
                'networkport',
                null,
                ['itemtype' => $itemtype, 'items_id' => $newItemsId],
                ['networkports_id' => (int) $after['id'], 'port_name' => $after['name']]
            );
        }

        $oldMac = (string) ($before['mac'] ?? '');
        $newMac = (string) ($after['mac'] ?? '');
        if ($oldMac !== $newMac) {
            $event = $oldMac === '' ? 'wifi.mac.added' : ($newMac === '' ? 'wifi.mac.removed' : 'wifi.mac.changed');
            AuditRepository::record(
                $event,
                $itemtype,
                $newItemsId,
                $entityId,
                'mac',
                'mac',
                $oldMac,
                $newMac,
                ['networkports_id' => (int) $after['id'], 'port_name' => $after['name']]
            );
        }
    }

    private static function recordPortRemoval(CommonDBTM $port, string $event): void
    {
        $before = AssetSnapshot::capturePort((int) $port->getID());
        if ($before === []) {
            return;
        }
        $itemtype = (string) $before['itemtype'];
        if (!AssetRegistry::isSupported($itemtype) || !AuditRepository::isEnabled($itemtype)) {
            return;
        }
        if (($before['mac'] ?? '') !== '') {
            AuditRepository::record($event, $itemtype, (int) $before['items_id'], 0, 'mac', 'mac', $before['mac'], null, ['networkports_id' => (int) $before['id']]);
        }
    }



    /**
     * Record the first SSID assignment after Fields creates its container row.
     */
    public static function postFieldsWifiAdd(CommonDBTM $item): void
    {
        global $DB;

        $id = (int) $item->getID();
        if ($id <= 0) {
            return;
        }

        $table = getTableForItemType($item::class);
        if (!$DB->tableExists($table)) {
            return;
        }

        $row = $DB->request([
            'SELECT' => ['items_id', 'plugin_fields_ssidfielddropdowns_id'],
            'FROM' => $table,
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ])->current();

        if (!$row) {
            return;
        }

        $itemsId = (int) ($row['items_id'] ?? 0);
        $newId = (int) ($row['plugin_fields_ssidfielddropdowns_id'] ?? 0);
        $itemtype = self::getAssetItemtypeForFieldsClass($item::class);
        if ($itemsId <= 0 || $newId <= 0 || $itemtype === null || !AuditRepository::isEnabled($itemtype)) {
            return;
        }

        $asset = AssetSnapshot::capture($itemtype, $itemsId);
        $entitiesId = (int) ($asset['asset']['entities_id'] ?? 0);

        AuditRepository::record(
            'wifi.ssid.added',
            $itemtype,
            $itemsId,
            $entitiesId,
            'ssid',
            'ssid',
            self::getSsidDropdownValue(0),
            self::getSsidDropdownValue($newId),
            [
                'source' => 'fields_container_add',
                'fields_record_id' => $id,
                'fields_class' => $item::class,
            ]
        );
    }

    /**
     * Fields persists custom container values through a generated CommonDBTM
     * class, not through the parent asset update. Capture the old value here.
     */
    public static function preFieldsWifiUpdate(CommonDBTM $item): void
    {
        $id = (int) $item->getID();
        if ($id <= 0) {
            return;
        }

        self::$before[self::key($item::class, $id)] = [
            'items_id' => (int) ($item->fields['items_id'] ?? $item->input['items_id'] ?? 0),
            'dropdown_id' => (int) ($item->fields['plugin_fields_ssidfielddropdowns_id'] ?? 0),
        ];
    }

    /**
     * Fields invokes update() on its generated wifi class. This hook runs
     * after that row has been persisted, so the comparison is independent
     * of the parent asset hook execution order.
     */
    public static function postFieldsWifiUpdate(CommonDBTM $item): void
    {
        $id = (int) $item->getID();
        $key = self::key($item::class, $id);
        $before = self::$before[$key] ?? [];
        unset(self::$before[$key]);

        global $DB;

        $itemtype = self::getAssetItemtypeForFieldsClass($item::class);
        if ($id <= 0 || $before === [] || $itemtype === null) {
            return;
        }

        // Read the persisted row instead of relying on the in-memory object.
        $table = getTableForItemType($item::class);
        $row = $DB->request([
            'SELECT' => ['items_id', 'plugin_fields_ssidfielddropdowns_id'],
            'FROM' => $table,
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ])->current();

        $itemsId = (int) ($row['items_id'] ?? $before['items_id'] ?? 0);
        if ($itemsId <= 0 || !AuditRepository::isEnabled($itemtype)) {
            return;
        }

        $oldId = (int) ($before['dropdown_id'] ?? 0);
        $newId = (int) ($row['plugin_fields_ssidfielddropdowns_id'] ?? 0);
        if ($oldId === $newId) {
            return;
        }

        $asset = AssetSnapshot::capture($itemtype, $itemsId);
        $entitiesId = (int) ($asset['asset']['entities_id'] ?? 0);
        $oldValue = self::getSsidDropdownValue($oldId);
        $newValue = self::getSsidDropdownValue($newId);
        $event = $oldId === 0 ? 'wifi.ssid.added' : ($newId === 0 ? 'wifi.ssid.removed' : 'wifi.ssid.changed');

        AuditRepository::record(
            $event,
            $itemtype,
            $itemsId,
            $entitiesId,
            'ssid',
            'ssid',
            $oldValue,
            $newValue,
            [
                'source' => 'fields_container',
                'fields_record_id' => $id,
                'fields_class' => $item::class,
            ]
        );
    }

    private static function getAssetItemtypeForFieldsClass(string $fieldsClass): ?string
    {
        if (!class_exists('PluginFieldsContainer')) {
            return null;
        }

        foreach (AssetRegistry::getItemtypes() as $itemtype) {
            try {
                if (\PluginFieldsContainer::getClassname($itemtype, 'wifi') === $fieldsClass) {
                    return $itemtype;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private static function getSsidDropdownValue(int $dropdownId): array
    {
        global $DB;

        if ($dropdownId <= 0 || !$DB->tableExists('glpi_plugin_fields_ssidfielddropdowns')) {
            return ['id' => 0, 'name' => '', 'completename' => '', 'radius_value' => ''];
        }

        $row = $DB->request([
            'SELECT' => ['id', 'name', 'completename', 'comment'],
            'FROM' => 'glpi_plugin_fields_ssidfielddropdowns',
            'WHERE' => ['id' => $dropdownId],
            'LIMIT' => 1,
        ])->current();

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'completename' => (string) ($row['completename'] ?? ''),
            'radius_value' => (string) ($row['comment'] ?? ''),
        ];
    }

    public static function preSsidDropdownUpdate(CommonDBTM $item): void
    {
        $id = (int) $item->getID();
        if ($id <= 0) {
            return;
        }

        self::$before[self::key('PluginFieldsSsidfielddropdown', $id)] = [
            'id' => $id,
            'name' => (string) ($item->fields['name'] ?? ''),
            'completename' => (string) ($item->fields['completename'] ?? ''),
            'comment' => (string) ($item->fields['comment'] ?? ''),
        ];
    }

    public static function postSsidDropdownUpdate(CommonDBTM $item): void
    {
        global $DB;

        $id = (int) $item->getID();
        $key = self::key('PluginFieldsSsidfielddropdown', $id);
        $before = self::$before[$key] ?? [];
        unset(self::$before[$key]);

        if ($id <= 0 || $before === []) {
            return;
        }

        $after = $DB->request([
            'SELECT' => ['id', 'name', 'completename', 'comment'],
            'FROM' => 'glpi_plugin_fields_ssidfielddropdowns',
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ])->current();

        if (!$after) {
            return;
        }

        $after = [
            'id' => (int) $after['id'],
            'name' => (string) ($after['name'] ?? ''),
            'completename' => (string) ($after['completename'] ?? ''),
            'comment' => (string) ($after['comment'] ?? ''),
        ];

        $changedName = $before['name'] !== $after['name']
            || $before['completename'] !== $after['completename'];
        $changedRadius = $before['comment'] !== $after['comment'];

        if (!$changedName && !$changedRadius) {
            return;
        }

        foreach (AssetRegistry::getItemtypes() as $itemtype) {
            $table = AssetSnapshot::getWifiFieldsTable($itemtype);
            if (
                !AuditRepository::isEnabled($itemtype)
                || $table === null
                || !$DB->tableExists($table)
            ) {
                continue;
            }

            $criteria = ['plugin_fields_ssidfielddropdowns_id' => $id];
            if ($DB->fieldExists($table, 'itemtype')) {
                $criteria['itemtype'] = $itemtype;
            }

            $iterator = $DB->request([
                'SELECT' => ['items_id'],
                'FROM' => $table,
                'WHERE' => $criteria,
            ]);

            foreach ($iterator as $relation) {
                $itemsId = (int) ($relation['items_id'] ?? 0);
                if ($itemsId <= 0) {
                    continue;
                }

                $snapshot = AssetSnapshot::capture($itemtype, $itemsId);
                $entitiesId = (int) ($snapshot['asset']['entities_id'] ?? 0);

                if ($changedName) {
                    AuditRepository::record(
                        'wifi.ssid.changed',
                        $itemtype,
                        $itemsId,
                        $entitiesId,
                        'ssid',
                        'ssid_definition',
                        [
                            'name' => $before['name'],
                            'completename' => $before['completename'],
                        ],
                        [
                            'name' => $after['name'],
                            'completename' => $after['completename'],
                        ],
                        ['ssid_dropdown_id' => $id, 'source' => 'fields_dropdown']
                    );
                }

                if ($changedRadius) {
                    AuditRepository::record(
                        'wifi.vlan.changed',
                        $itemtype,
                        $itemsId,
                        $entitiesId,
                        'vlan',
                        'radius_value',
                        $before['comment'],
                        $after['comment'],
                        [
                            'ssid_dropdown_id' => $id,
                            'ssid_name' => $after['completename'] !== '' ? $after['completename'] : $after['name'],
                            'source' => 'fields_dropdown',
                        ]
                    );
                }
            }
        }
    }

    private static function key(string $itemtype, int $id): string
    {
        return $itemtype . ':' . $id;
    }
}
