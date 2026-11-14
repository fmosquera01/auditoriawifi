<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

final class AssetRegistry
{
    /**
     * Only assets used by Songa's RADIUS/WiFi inventory are audited.
     *
     * @return array<string, string> GLPI itemtype => display label
     */
    public static function getAssets(): array
    {
        return [
            \Computer::class => 'Computadoras',
            \Printer::class => 'Impresoras',
            \Phone::class => 'Teléfonos',
            \Glpi\CustomAsset\mobileAsset::class => 'Mobile',
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
