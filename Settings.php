<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailSignupFastpanel;

use Aurora\System\SettingsProperty;

/**
 * @property bool $Disabled
 * @property string $FastpanelURL
 * @property string $FastpanelAdminUser
 * @property string $FastpanelAdminPass
 * @property int $UserDefaultQuotaMB
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "FastpanelURL" => new SettingsProperty(
                "http://localhost:8888",
                "string",
                null,
                "Defines main URL of Fastpanel installation",
            ),
            "FastpanelAdminUser" => new SettingsProperty(
                "fastuser",
                "string",
                null,
                "Admin username of Fastpanel installation",
            ),
            "FastpanelAdminPass" => new SettingsProperty(
                "",
                "string",
                null,
                "Admin password of Fastpanel installation",
            ),
            "UserDefaultQuotaMB" => new SettingsProperty(
                20,
                "int",
                null,
                "Default quota of new email accounts created on Fastpanel",
            ),
        ];
    }
}
