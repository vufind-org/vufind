<?php
/**
 * Wrapper class for handling logged-in user in session.
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
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;

use Finna\Db\Row\User;
use VuFind\Auth\AbstractBase;
use VuFind\Auth\ChoiceAuth;
use VuFind\Exception\Auth as AuthException;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager extends \VuFind\Auth\Manager
{
    /**
     * Get the active authentication handler.
     *
     * @return AbstractBase
     */
    public function getActiveAuth()
    {
        return $this->getAuth($this->activeAuth);
    }

    /**
     * Get secondary login field label (if any)
     *
     * @param string $target Login target (only for MultiILS)
     *
     * @return string|false
     */
    public function getSecondaryLoginFieldLabel($target = '')
    {
        $auth = $this->getAuth();
        if (is_callable([$auth, 'getSecondaryLoginFieldLabel'])) {
            return $auth->getSecondaryLoginFieldLabel($target);
        }
        return false;
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return User Object representing logged-in user.
     */
    public function login($request)
    {
        $user = parent::login($request);
        $auth = $this->getAuth();

        if ($auth instanceof ChoiceAuth) {
            $method = $auth->getSelectedAuthOption();
        } else {
            $method = $this->activeAuth;
        }

        $user->finna_auth_method = strtolower($method);
        $user->finna_last_login = date('Y-m-d H:i:s');
        $user->save();

        return $user;
    }
}
