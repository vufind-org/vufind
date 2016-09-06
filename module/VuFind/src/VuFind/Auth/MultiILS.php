<?php
/**
 * Multiple ILS authentication module that works with MultiBackend driver
 *
 * PHP version 5
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
use VuFind\Exception\Auth as AuthException;
use VuFind\ILS\Driver\MultiBackend;

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
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $target = trim($request->getPost()->get('target'));
        $username = trim($request->getPost()->get('username'));
        $password = trim($request->getPost()->get('password'));
        if ($username == '' || $password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // We should have target either separately or already embedded into username
        if ($target) {
            $username = "$target.$username";
        }

        // Connect to catalog:
        try {
            $patron = $this->getCatalog()->patronLogin($username, $password);
        } catch (AuthException $e) {
            // Pass Auth exceptions through
            throw $e;
        } catch (\Exception $e) {
            throw new AuthException('authentication_error_technical');
        }

        // Did the patron successfully log in?
        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new AuthException('authentication_error_invalid');
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
        return parent::setCatalog($connection);
    }
}
