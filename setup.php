<?php
declare(strict_types=1);

use Glpi\Plugin\Hooks;
use GlpiPlugin\Auditoriawifi\AssetRegistry;
use GlpiPlugin\Auditoriawifi\EventObserver;
use GlpiPlugin\Auditoriawifi\ConfigPage;

define('PLUGIN_AUDITORIAWIFI_VERSION', '0.1.8');

require_once __DIR__ . '/src/AssetRegistry.php';
require_once __DIR__ . '/src/AuditRepository.php';
require_once __DIR__ . '/src/AlertManager.php';
require_once __DIR__ . '/src/AssetSnapshot.php';
require_once __DIR__ . '/src/EventObserver.php';
require_once __DIR__ . '/src/ConfigPage.php';

function plugin_init_auditoriawifi(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['auditoriawifi'] = true;
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['auditoriawifi'] = [
        'plugins' => ConfigPage::class,
    ];

    foreach (AssetRegistry::getItemtypes() as $itemtype) {
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['auditoriawifi'][$itemtype] = [EventObserver::class, 'preAssetAdd'];
        $PLUGIN_HOOKS[Hooks::ITEM_ADD]['auditoriawifi'][$itemtype] = [EventObserver::class, 'postAssetAdd'];
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['auditoriawifi'][$itemtype] = [EventObserver::class, 'preAssetUpdate'];
        $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['auditoriawifi'][$itemtype] = [EventObserver::class, 'postAssetUpdate'];
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_DELETE]['auditoriawifi'][$itemtype] = [EventObserver::class, 'preAssetDelete'];
        $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['auditoriawifi'][$itemtype] = [EventObserver::class, 'preAssetPurge'];
    }

    // Fields stores each container in a generated class/table. Observe the
    // wifi container itself so SSID changes are captured at the real write.
    if (class_exists('PluginFieldsContainer')) {
        foreach (AssetRegistry::getItemtypes() as $itemtype) {
            try {
                $fieldsClass = \PluginFieldsContainer::getClassname($itemtype, 'wifi');
                $PLUGIN_HOOKS[Hooks::ITEM_ADD]['auditoriawifi'][$fieldsClass] = [EventObserver::class, 'postFieldsWifiAdd'];
                $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['auditoriawifi'][$fieldsClass] = [EventObserver::class, 'preFieldsWifiUpdate'];
                $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['auditoriawifi'][$fieldsClass] = [EventObserver::class, 'postFieldsWifiUpdate'];
            } catch (\Throwable $e) {
                // A given asset type may not have a generated wifi container.
            }
        }
    }

    $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['auditoriawifi']['PluginFieldsSsidfielddropdown'] = [EventObserver::class, 'preSsidDropdownUpdate'];
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['auditoriawifi']['PluginFieldsSsidfielddropdown'] = [EventObserver::class, 'postSsidDropdownUpdate'];

    $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'preNetworkPortUpdate'];
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'postNetworkPortUpdate'];
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'preNetworkPortAdd'];
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'postNetworkPortAdd'];
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_DELETE]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'preNetworkPortDelete'];
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['auditoriawifi']['NetworkPort'] = [EventObserver::class, 'preNetworkPortPurge'];
}

function plugin_version_auditoriawifi(): array
{
    return [
        'name' => 'Auditoría WiFi',
        'version' => PLUGIN_AUDITORIAWIFI_VERSION,
        'author' => 'Songa - Infraestructura IT',
        'license' => 'GPLv3+',
        'homepage' => 'https://github.com/fmosquera01/auditoriawifi',
        'requirements' => [
            'glpi' => ['min' => '11.0.11', 'max' => '11.99.99'],
            'php' => ['min' => '8.2.0'],
        ],
    ];
}

function plugin_auditoriawifi_check_prerequisites(): bool
{
    return defined('GLPI_VERSION')
        && version_compare(GLPI_VERSION, '11.0.11', '>=')
        && version_compare(GLPI_VERSION, '12.0.0', '<')
        && version_compare(PHP_VERSION, '8.2.0', '>=');
}

function plugin_auditoriawifi_check_config($verbose = false): bool
{
    return true;
}
