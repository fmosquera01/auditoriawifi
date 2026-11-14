<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonGLPI;

final class ConfigPage extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return __('Auditoría WiFi', 'auditoriawifi');
    }

    public static function getMenuName($nb = 0): string
    {
        return __('Auditoría WiFi', 'auditoriawifi');
    }

    public static function getMenuContent(): array
    {
        return [
            'title' => self::getMenuName(),
            'page' => '/plugins/auditoriawifi/front/alerts.php',
            'icon' => 'ti ti-shield-lock',
            'options' => [
                'alerts' => [
                    'title' => __('Visualizar alertas', 'auditoriawifi'),
                    'page' => '/plugins/auditoriawifi/front/alerts.php',
                    'icon' => 'ti ti-bell',
                ],
                'config' => [
                    'title' => __('Parámetros', 'auditoriawifi'),
                    'page' => '/plugins/auditoriawifi/front/config.php',
                    'icon' => 'ti ti-settings',
                ],
            ],
        ];
    }
}
