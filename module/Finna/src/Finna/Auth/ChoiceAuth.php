<?php
/**
 * MultiAuth Authentication plugin
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind2
 * @package  Authentication
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Auth;

use Finna\Db\Row\User;
use VuFind\Exception\Auth as AuthException;
use Zend\Http\PhpEnvironment\Request;

/**
 * MultiAuth Authentication plugin.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ChoiceAuth extends \VuFind\Auth\ChoiceAuth
{
    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $user = parent::authenticate($request);

        $user->finna_auth_method = $this->strategy;
        $user->finna_last_login = date('Y-m-d H:i:s');
        $user->save();

        return $user;
    }
}
