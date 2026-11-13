<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

final class AssetRegistry
{
    /** @return array<string, string> GLPI itemtype => display label */
    public static function getAssets(): array
    {
        return [
            'Computer' => 'Computadoras',
            'Printer' => 'Impresoras',
            'Phone' => 'Teléfonos',
            'PluginGenericobjectMobile' => 'Mobile',
        ];
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
