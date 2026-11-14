<?php
declare(strict_types=1);

use GlpiPlugin\Auditoriawifi\AuditRepository;

function plugin_auditoriawifi_install(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_AUDITORIAWIFI_VERSION);
    $charset = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();

    $tables = [
        'glpi_plugin_auditoriawifi_events' => "CREATE TABLE IF NOT EXISTS `glpi_plugin_auditoriawifi_events` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `event_key` varchar(80) NOT NULL,
            `itemtype` varchar(100) NOT NULL,
            `items_id` int unsigned NOT NULL,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `event_group` varchar(30) NOT NULL,
            `field_name` varchar(80) NOT NULL DEFAULT '',
            `old_value` longtext DEFAULT NULL,
            `new_value` longtext DEFAULT NULL,
            `details_json` longtext DEFAULT NULL,
            `users_id` int unsigned NOT NULL DEFAULT 0,
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_asset_history` (`itemtype`, `items_id`, `date_creation`),
            KEY `idx_event_date` (`event_key`, `date_creation`),
            KEY `idx_user_date` (`users_id`, `date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
        'glpi_plugin_auditoriawifi_configs' => "CREATE TABLE IF NOT EXISTS `glpi_plugin_auditoriawifi_configs` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `itemtype` varchar(100) NOT NULL,
            `is_enabled` tinyint NOT NULL DEFAULT 1,
            `alerts_json` longtext DEFAULT NULL,
            `date_mod` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity_itemtype` (`itemtype`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation}",
    ];

    foreach ($tables as $table => $sql) {
        if (!$DB->tableExists($table)) {
            $migration->addPostQuery($sql);
        }
    }

    $migration->executeMigration();

    foreach (\\GlpiPlugin\\Auditoriawifi\\AssetRegistry::getItemtypes() as $itemtype) {
        $existing = $DB->request([
            'COUNT' => 'c',
            'FROM' => 'glpi_plugin_auditoriawifi_configs',
            'WHERE' => ['itemtype' => $itemtype],
        ])->current();
        if ((int) ($existing['c'] ?? 0) === 0) {
            $DB->insert('glpi_plugin_auditoriawifi_configs', [
                'itemtype' => $itemtype,
                'is_enabled' => 1,
                'alerts_json' => json_encode([], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    return true;
}

/**
 * Run the idempotent installer when upgrading.
 * Existing event history and alert selections are preserved.
 */
function plugin_auditoriawifi_upgrade(string $oldVersion): bool
{
    return plugin_auditoriawifi_install();
}

/** Keep the audit history when the plugin is uninstalled. */
function plugin_auditoriawifi_uninstall(): bool
{
    return true;
}
