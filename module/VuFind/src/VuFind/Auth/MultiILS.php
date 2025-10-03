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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\ILS\Connection;
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
     * @return string
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

    /**
     * Does this authentication method support password recovery
     *
     * @param ?string $target Authentication target for methods that support target selection
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function supportsPasswordRecovery(?string $target = null)
    {
        if (!$target) {
            throw new \Exception(__METHOD__ . ' requires the target parameter!');
        }
        // If a target is specified, use an arbitrary cat_username with the correct target prefix:
        $recoveryConfig = $this->getCatalog()->checkFunction(
            'resetPassword',
            ['cat_username' => "$target.123"]
        );
        return $recoveryConfig ? true : false;
    }

    /**
     * Get password recovery data (such as a user id or recovery token) based on form data submitted by the user.
     *
     * @param array $params Request params (form data)
     *
     * @return ?array Null if user not found, or associative array with following keys:
     *   string email    User's email address
     *   string username Username (optional, for display)
     *   array  details  Array of user details required for resetPassword request
     */
    public function getPasswordRecoveryData(array $params): ?array
    {
        if (!($target = $params['target'] ?? null)) {
            throw new \Exception(__METHOD__ . ' requires the target parameter!');
        }
        $params['cat_username'] = $target . '.' . $params['cat_username'];

        $result = $this->getCatalog()->getPasswordRecoveryData($params);
        if (!$result['success']) {
            throw new AuthException($result['error']);
        }
        $recoveryData = $result['data'];
        $recoveryData['target'] = $target;
        return $recoveryData;
    }
}
