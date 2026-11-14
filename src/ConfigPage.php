<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonGLPI;

final class ConfigPage extends CommonGLPI
{
    public static $rightname = 'auditoriawifi:config';
    public static function getTypeName($nb = 0): string
    {
        return __('Auditoría WiFi', 'auditoriawifi');
    }

    public function getRights($interface = 'central'): array
    {
        return [
            READ   => __('Read'),
            UPDATE => __('Update'),
        ];
    }

    public static function getMenuName($nb = 0): string
    {
        return __('Auditoría WiFi', 'auditoriawifi');
    }

    public static function getMenuContent(): array
    {
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/auditoriawifi/front/home.php',
            'icon'  => 'ti ti-shield-lock',
        ];
    }
}
