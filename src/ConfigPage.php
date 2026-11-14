<?php
declare(strict_types=1);

namespace GlpiPlugin\\Auditoriawifi;

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
        $configPage = '/plugins/auditoriawifi/front/config.php';
        $eventsPage = '/plugins/auditoriawifi/front/alerts.php';

        return [
            'title' => self::getMenuName(),
            'page' => $configPage,
            'icon' => 'ti ti-shield-lock',
            'options' => [
                'config' => [
                    'title' => __('Configuración', 'auditoriawifi'),
                    'page' => $configPage,
                    'icon' => 'ti ti-settings',
                    'links' => [
                        'search' => $configPage,
                    ],
                ],
                'events' => [
                    'title' => __('Eventos', 'auditoriawifi'),
                    'page' => $eventsPage,
                    'icon' => 'ti ti-list-check',
                    'links' => [
                        'search' => $eventsPage,
                    ],
                ],
            ],
        ];
    }
}
