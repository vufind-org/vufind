<?php

/**
 * Multiple ILS authentication module that works with MultiBackend driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2013.
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;
use VuFind\ILS\Driver\MultiBackend;

use function in_array;

/**
 * Multiple ILS authentication module that works with MultiBackend driver
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class MultiILS extends ILS
{
    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $username = trim($request->getPost()->get('username', ''));
        $password = trim($request->getPost()->get('password', ''));
        $target = trim($request->getPost()->get('target', ''));
        $loginMethod = $this->getILSLoginMethod($target);
        $rememberMe = (bool)$request->getPost()->get('remember_me', false);

        // We should have target either separately or already embedded into username
        if ($target) {
            $username = "$target.$username";
        } else {
            [$target] = explode('.', $username);
        }

        // Check that the target is valid:
        if (!in_array($target, $this->getLoginTargets())) {
            throw new AuthException('authentication_error_admin');
        }

        return $this->handleLogin($username, $password, $loginMethod, $rememberMe);
    }

    /**
     * Get login targets (ILS drivers/source ID's)
     *
     * @return array
     */
    public function getLoginTargets()
    {
        return $this->getCatalog()->getLoginDrivers();
    }

    /**
     * Get default login target (ILS driver/source ID)
     *
     * @return array
     */
    public function getDefaultLoginTarget()
    {
        return $this->getCatalog()->getDefaultLoginDriver();
    }

    /**
     * Set the ILS connection for this object.
     *
     * @param \VuFind\ILS\Connection $connection ILS connection to set
     *
     * @return void
     */
    public function setCatalog(\VuFind\ILS\Connection $connection)
    {
        // Right now, MultiILS authentication only works with the MultiBackend
        // driver; if other ILS drivers eventually support this option, we
        // should define an interface containing getLoginDrivers() and
        // getDefaultLoginDriver().
        if (!($connection->getDriver() instanceof MultiBackend)) {
            throw new \Exception(
                'MultiILS authentication requires MultiBackend ILS driver.'
            );
        }
        parent::setCatalog($connection);
    }
}
