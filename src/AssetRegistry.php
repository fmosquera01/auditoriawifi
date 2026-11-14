<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonDBTM;
use Glpi\Asset\Asset;
use Glpi\Asset\AssetDefinitionManager;

final class AssetRegistry
{
    /** @return array<string, string> GLPI itemtype => display label */
    public static function getAssets(): array
    {
        global $CFG_GLPI;

        $assets = [
            \Computer::class => 'Computadoras',
            \Printer::class => 'Impresoras',
            \Phone::class => 'Teléfonos',
        ];

        // Asset Definitions generate their real PHP itemtypes dynamically.
        if (class_exists(AssetDefinitionManager::class)) {
            try {
                AssetDefinitionManager::getInstance()->bootDefinitions();
            } catch (\Throwable $e) {
                // Keep native asset support if definitions cannot be booted.
            }
        }

        foreach (($CFG_GLPI['asset_types'] ?? []) as $itemtype) {
            if (
                !is_string($itemtype)
                || !class_exists($itemtype)
                || !is_a($itemtype, Asset::class, true)
                || !is_a($itemtype, CommonDBTM::class, true)
            ) {
                continue;
            }

            $label = $itemtype;
            try {
                $label = $itemtype::getTypeName(1);
            } catch (\Throwable $e) {
                // The class name remains a safe fallback label.
            }
            $assets[$itemtype] = (string) $label;
        }

        return $assets;
    }

    /** @return string[] */
    public static function getItemtypes(): array
    {
        return array_keys(self::getAssets());
    }

    public static function isSupported(string $itemtype): bool
    {
        return isset(self::getAssets()[$itemtype]);
    }
}
