<?php

/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailSignupFastpanel;

/**
 * Allows users to create new email accounts for themselves on Fastpanel.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    public function init()
    {
        $this->subscribeEvent('MailSignup::Signup::before', [$this, 'onAfterSignup']);
    }

    /**
     *
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     *
     * @return Settings
     */
    protected function GetModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Send GET request via cURL
     * @param string $sUrl
     * @param string $sToken
     * @return object|bool
     */
    private function getdata($sUrl, $sToken="")
    {
        $rCurl = curl_init();
        $acurlOpt = array(
            CURLOPT_URL => $sUrl,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        );
        if ($sToken!="") {
            array_push($acurlOpt[CURLOPT_HTTPHEADER], "Authorization: Bearer ".$sToken);
        }
        curl_setopt_array($rCurl, $acurlOpt);
        $mResult = curl_exec($rCurl);
        curl_close($rCurl);
        $oResult = ($mResult !== false) ? json_decode($mResult) : false;
        return $oResult;
    }

    /**
     * Send POST request via cURL
     * @param string $sUrl
     * @param string $aPost
     * @param string $sToken
     * @return object|bool
     */
    private function postdata($sUrl, $aPost, $sToken="")
    {
        $rCurl = curl_init();
        $acurlOpt = array(
            CURLOPT_URL => $sUrl,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $aPost,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        );
        if ($sToken!="") {
            array_push($acurlOpt[CURLOPT_HTTPHEADER], "Authorization: Bearer ".$sToken);
        }
        curl_setopt_array($rCurl, $acurlOpt);
        $mResult = curl_exec($rCurl);
        curl_close($rCurl);
        $oResult = ($mResult !== false) ? json_decode($mResult) : false;
        return $oResult;
    }

    /**
     * Creates account with credentials specified in registration form
     *
     * @param array $aArgs New account credentials.
     * @param mixed $mResult Is passed by reference.
     */
    public function onAfterSignup($aArgs, &$mResult)
    {
        if (isset($aArgs['Login']) && isset($aArgs['Password']) && !empty(trim($aArgs['Password'])) && !empty(trim($aArgs['Login']))) {
            $sLogin = trim($aArgs['Login']);
            $sPassword = trim($aArgs['Password']);
            $sFriendlyName = isset($aArgs['Name']) ? trim($aArgs['Name']) : '';
            $bSignMe = isset($aArgs['SignMe']) ? (bool) $aArgs['SignMe'] : false;
            $iQuota = (int) $this->getConfig('UserDefaultQuotaMB', 20);

            $bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
            [$sUsername, $sDomain] = explode("@", $sLogin);
            if (!empty($sDomain)) {
                $sFastpanelURL = rtrim($this->getConfig('FastpanelURL', ''), "/");
                $sFastpanelAdminUser = $this->getConfig('FastpanelAdminUser', '');
                $sFastpanelAdminPass = $this->getConfig('FastpanelAdminPass', '');

                $aPost = array("password"=>$sFastpanelAdminPass, "username"=>$sFastpanelAdminUser);
                $oRes1 = $this->postdata($sFastpanelURL."/login", json_encode($aPost));

                if ($oRes1===false) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel admin auth general error");
                }

                if (isset($oRes1->code) && isset($oRes1->message)) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel admin auth error ".$oRes1->code.": ".$oRes1->message);
                }

                if (!isset($oRes1->data->token)) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel admin auth failed");
                }

                $sToken = $oRes1->data->token;
                $oRes2 = $this->getdata($sFastpanelURL."/api/email/domains", $sToken);
                if (($oRes2===false)||(!isset($oRes2->data))) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel API error: could not get list of domains");
                }

                $aDomainList = $oRes2->data;
                $iDomainId = null;
                foreach ($aDomainList as $oDomainListItem) {
                    if ($oDomainListItem->name == $sDomain) {
                        $iDomainId = $oDomainListItem->id;
                    }
                }

                if ($iDomainId == null) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel API error: could not locate email domain ".$sDomain);
                }

                $aPost = array("login" => $sUsername, "password" => $sPassword, "quota" => $iQuota, "redirects" => array(), "aliases" => array(), "spam_to_junk" => false);
                $oRes3 = $this->postdata($sFastpanelURL."/api/email/domains/".$iDomainId."/boxs", json_encode($aPost), $sToken);

                if (isset($oRes3->errors->password)) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel API error: ".$oRes3->errors->password);
                }

                if (!isset($oRes3->data->id)) {
                    throw new \Aurora\System\Exceptions\ApiException(0, null, "Fastpanel API error: signup failure");
                }

                $iUserId = \Aurora\Modules\Core\Module::Decorator()->CreateUser(0, $sLogin);
                $oUser = \Aurora\System\Api::getUserById((int) $iUserId);
                try {
                    $oAccount = \Aurora\Modules\Mail\Module::Decorator()->CreateAccount($oUser->Id, $sFriendlyName, $sLogin, $sLogin, $sPassword);
                    if ($oAccount instanceof \Aurora\Modules\Mail\Models\MailAccount) {
                        $iTime = $bSignMe ? 0 : time();
                        $sAuthToken = \Aurora\System\Api::UserSession()->Set(
                            [
                                'token'		=> 'auth',
                                'sign-me'		=> $bSignMe,
                                'id'			=> $oAccount->IdUser,
                                'account'		=> $oAccount->Id,
                                'account_type'	=> $oAccount->getName()
                            ],
                            $iTime
                        );
                        $mResult = ['AuthToken' => $sAuthToken];
                    }
                } catch (\Exception $oException) {
                    if ($oException instanceof \Aurora\Modules\Mail\Exceptions\Exception &&
                        $oException->getCode() === \Aurora\Modules\Mail\Enums\ErrorCodes::CannotLoginCredentialsIncorrect) {
                        \Aurora\Modules\Core\Module::Decorator()->DeleteUser($oUser->Id);
                    }
                    throw $oException;
                }
            }
            \Aurora\System\Api::skipCheckUserRole($bPrevState);
        }
        return true; // break subscriptions to prevent account creation in other modules
    }
}
