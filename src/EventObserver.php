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
        self::recordSnapshotDifferences($itemtype, (int) $item->getID(), $before);
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

    private static function recordSnapshotDifferences(string $itemtype, int $itemsId, array $before): void
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

        $oldMac = (string) ($before['mac'] ?? '');
        $newMac = (string) ($after['mac'] ?? '');
        if ($oldMac === $newMac) {
            return;
        }
        $event = $oldMac === '' ? 'wifi.mac.added' : ($newMac === '' ? 'wifi.mac.removed' : 'wifi.mac.changed');
        AuditRepository::record(
            $event,
            $itemtype,
            (int) $after['items_id'],
            0,
            'mac',
            'mac',
            $oldMac,
            $newMac,
            ['networkports_id' => (int) $after['id'], 'port_name' => $after['name']]
        );
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

    private static function key(string $itemtype, int $id): string
    {
        return $itemtype . ':' . $id;
    }
}
