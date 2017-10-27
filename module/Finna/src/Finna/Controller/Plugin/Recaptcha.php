<?php
/**
 * VuFind Recaptcha controller plugin
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Plugin
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller\Plugin;

use Zend\ServiceManager\ServiceManager;

/**
 * Recaptcha controller plugin.
 *
 * @category VuFind
 * @package  Plugin
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/
 */
class Recaptcha extends \VuFind\Controller\Plugin\Recaptcha
{
    /**
     * Bypassed authentication methods
     *
     * @var array
     */
    protected $bypassCaptcha = [];

    /**
     * Authentication manager
     *
     * @var ServiceManager
     */
    protected $authManager;

    /**
     * Recaptcha constructor.
     *
     * @param \ZendService\ReCaptcha\ReCaptcha $r           ReCaptcha object
     * @param \VuFind\Config                   $config      Config file
     * @param ServiceManager                   $authManager Authentication Manager
     *
     * @return Recaptcha
     */
    public function __construct($r, $config, $authManager)
    {
        parent::__construct($r, $config);
        $this->authManager = $authManager;
        if (!empty($config->Captcha->bypassCaptcha)) {
            $trimLowercase = function ($str) {
                return strtolower(trim($str));
            };

            $bypassCaptcha = $config->Captcha->bypassCaptcha->toArray();
            foreach ($bypassCaptcha as $domain => $authMethods) {
                $this->bypassCaptcha[$domain] = array_map(
                    $trimLowercase,
                    explode(',', $authMethods)
                );
            }
        }
    }

    /**
     * Return whether a specific form is set for Captcha in the config. Takes into
     * account authentication methods which should be bypassed.
     *
     * @param string|bool $domain The specific config term are we checking; ie. "sms"
     *
     * @return bool
     */
    public function active($domain = false)
    {
        if (!$domain || empty($this->bypassCaptcha[$domain])) {
            return parent::active($domain);
        }

        $user = $this->authManager->isLoggedIn();

        $bypassCaptcha = $user && in_array(
            strtolower($user->finna_auth_method),
            $this->bypassCaptcha[$domain]
        );
        return $bypassCaptcha ? false : parent::active($domain);
    }
}
