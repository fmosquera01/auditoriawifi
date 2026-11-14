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
        $configPage = '/plugins/auditoriawifi/front/config.php';
        $eventsPage = '/plugins/auditoriawifi/front/alerts.php';

        return [
            'title' => self::getMenuName(),
            'page' => $configPage,
            'icon' => 'ti ti-shield-lock',
            // Custom link keys are rendered by GLPI in the contextual bar.
            // The built-in "search" key is intentionally hidden by GLPI.
            'links' => [
                __('Configurar parámetros', 'auditoriawifi') => $configPage,
                __('Ver eventos', 'auditoriawifi') => $eventsPage,
            ],
            'options' => [
                'config' => [
                    'title' => __('Configuración', 'auditoriawifi'),
                    'page' => $configPage,
                ],
                'events' => [
                    'title' => __('Eventos', 'auditoriawifi'),
                    'page' => $eventsPage,
                ],
            ],
        ];
    }
}
