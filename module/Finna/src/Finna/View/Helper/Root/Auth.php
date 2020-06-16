<?php
/**
 * Authentication view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root;

use Laminas\Http\PhpEnvironment\Request;

/**
 * Authentication view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Auth extends \VuFind\View\Helper\Root\Auth
{
    /**
     * Constructor
     *
     * @param \VuFind\Auth\Manager          $manager          Authentication manager
     * @param \VuFind\Auth\ILSAuthenticator $ilsAuthenticator ILS Authenticator
     * @param Request                       $request          Request
     */
    public function __construct(\VuFind\Auth\Manager $manager,
        \VuFind\Auth\ILSAuthenticator $ilsAuthenticator, Request $request
    ) {
        parent::__construct($manager, $ilsAuthenticator);
        $this->request = $request;
    }

    /**
     * Get auth method requested in current request
     *
     * @return string
     */
    public function getRequestedAuthMethod()
    {
        return $this->request->getPost('auth_method', '');
    }
}
