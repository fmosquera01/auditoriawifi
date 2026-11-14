<?php
declare(strict_types=1);

namespace GlpiPlugin\Auditoriawifi;

use CommonDBTM;
use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use Session;

/**
 * Profile tab and rights declaration for Auditoría WiFi.
 */
final class Profile extends CommonDBTM
{
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0): string
    {
        return __('Auditoría WiFi', 'auditoriawifi');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && $item->getField('id')) {
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile && $item->getField('id')) {
            self::showForProfile((int) $item->getID());
        }

        return true;
    }

    public static function getAllRights($all = false): array
    {
        return [
            [
                'itemtype' => ConfigPage::class,
                'label'    => __('Configuración de Auditoría WiFi', 'auditoriawifi'),
                'field'    => 'auditoriawifi:config',
            ],
            [
                'itemtype' => ConfigPage::class,
                'label'    => __('Consultar eventos de Auditoría WiFi', 'auditoriawifi'),
                'field'    => 'auditoriawifi:events',
            ],
        ];
    }

    public static function showForProfile(int $profiles_id): void
    {
        $profile = new GlpiProfile();
        if (!$profile->getFromDB($profiles_id)) {
            return;
        }

        $canEdit = self::canUpdate();
        echo "<div class='spaced'>";
        echo "<form name='form' action='" . htmlspecialchars(GlpiProfile::getFormURL(), ENT_QUOTES, 'UTF-8') . "' method='post'>";
        echo Html::hidden('id', ['value' => $profiles_id]);

        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit' => $canEdit,
            'title'   => __('Permisos de Auditoría WiFi', 'auditoriawifi'),
        ]);

        if ($canEdit) {
            echo "<div class='text-center'>";
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
        }
        Html::closeForm();
        echo "</div>";
    }
}
