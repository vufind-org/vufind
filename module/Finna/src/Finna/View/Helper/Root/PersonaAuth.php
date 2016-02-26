<?php

/**
 * Helper to check if current user is authenticated with Mozilla Persona
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper to check if current user is authenticated with Mozilla Persona
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class PersonaAuth extends \Zend\View\Helper\AbstractHelper
{
    protected $serviceLocator;
    protected $config;

    /**
     * Constructor
     *
     * @param type $serviceLocator Service locator
     */
    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        $this->config = $this->serviceLocator->get('VuFind\Config')->get('config');
    }

    /**
     * Is current user logged in by Mozilla Persona authentication.
     *
     * @return type User's email or null
     */
    public function getUser()
    {
        $authManager = $this->serviceLocator->get('VuFind\AuthManager');
        $user = $authManager->isLoggedIn();
        if ($user === false) {
            return null;
        } else {
            $authMethod = $authManager->getAuthMethod();
            if ($authMethod == 'ChoiceAuth') {
                $currentAuth = $authManager->getActiveAuth();
                $authMethod = $currentAuth->getSelectedAuthOption();
            }
            if ($authMethod != 'MozillaPersona') {
                return null;
            }
            list(,$username) = explode(':', $user->username, 2);
            return $username;
        }
    }

    /**
     * Return Mozilla Persona Auto logout value from config file.
     *
     * @return boolean
     */
    public function getAutoLogout()
    {
        $autoLogout = $this->config->Authentication->mozillaPersonaAutoLogout;
        if (empty($autoLogout)) {
            return false;
        } else {
            return true;
        }
    }

}
