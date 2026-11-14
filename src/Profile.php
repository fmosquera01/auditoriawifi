<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonGLPI;
use Html;
use Session;

/**
 * Native GLPI profile permissions for Auditoría WiFi.
 */
final class Profile extends \Profile
{
    public static $rightname = 'plugin_auditoriawifi_config';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile && (int) $item->getField('id') > 0) {
            return self::createTabEntry(__('Auditoría WiFi', 'auditoriawifi'));
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof \Profile) {
            $profile = new self();
            $profile->showFormAuditoriaWifi((int) $item->getID());
        }

        return true;
    }

    private function showFormAuditoriaWifi(int $profiles_id): void
    {
        $targetProfile = new \Profile();
        if (!$targetProfile->can($profiles_id, UPDATE) || !$targetProfile->getFromDB($profiles_id)) {
            return;
        }

        $canEdit = Session::haveRight('profile', UPDATE);
        echo "<div class='spaced'>";

        if ($canEdit) {
            echo "<form method='post' action='" . htmlspecialchars(\Profile::getFormURL(), ENT_QUOTES, 'UTF-8') . "'>";
        }

        $rights = [
            [
                'itemtype' => ConfigPage::class,
                'label'    => __('Configuración de Auditoría WiFi', 'auditoriawifi'),
                'field'    => 'plugin_auditoriawifi_config',
            ],
            [
                'itemtype' => ConfigPage::class,
                'label'    => __('Consultar eventos de Auditoría WiFi', 'auditoriawifi'),
                'field'    => 'plugin_auditoriawifi_events',
            ],
        ];

        $targetProfile->displayRightsChoiceMatrix($rights, [
            'canedit' => $canEdit,
            'title'   => __('Permisos de Auditoría WiFi', 'auditoriawifi'),
        ]);

        if ($canEdit) {
            echo "<div class='text-center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo '</div>';
            Html::closeForm();
        }

        echo '</div>';
    }
}
