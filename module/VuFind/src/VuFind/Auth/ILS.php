<?php
/**
 * ILS authentication module.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
namespace VuFind\Auth;
use VuFind\Connection\Manager as ConnectionManager,
    VuFind\Db\Table\User as UserTable, VuFind\Exception\Auth as AuthException;

/**
 * ILS authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class ILS extends AbstractBase
{
    protected $catalog = null;

    /**
     * Get the ILS driver associated with this object (or load the default from
     * the connection manager.
     *
     * @return \VuFind\ILS\Driver\DriverInterface
     */
    public function getCatalog()
    {
        if (null === $this->catalog) {
            $this->catalog = ConnectionManager::connectToCatalog();
        }
        return $this->catalog;
    }

    /**
     * Set the ILS driver associated with this object.
     *
     * @param \VuFind\ILS\Driver\DriverInterface $driver Driver to set
     *
     * @return void
     */
    public function setCatalog(\VuFind\ILS\Driver\DriverInterface $driver)
    {
        $this->catalog = $driver;
    }

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
        $username = trim($request->getPost()->get('username'));
        $password = trim($request->getPost()->get('password'));
        if ($username == '' || $password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Connect to catalog:
        try {
            $patron = $this->getCatalog()->patronLogin($username, $password);
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
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Processed User object.
     */
    protected function processILSUser($info)
    {
        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $usernameField = isset($this->config->Authentication->ILS_username_field)
            ? $this->config->Authentication->ILS_username_field : 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $table = new UserTable();
        $user = $table->getByUsername($info[$usernameField]);

        // No need to store the ILS password in VuFind's main password field:
        $user->password = "";

        // Update user information based on ILS data:
        $user->firstname = !isset($info['firstname']) ? " " : $info['firstname'];
        $user->lastname = !isset($info['lastname']) ? " " : $info['lastname'];
        $user->cat_username = !isset($info['cat_username'])
            ? " " : $info['cat_username'];
        $user->cat_password = !isset($info['cat_password'])
            ? " " : $info['cat_password'];
        $user->email = !isset($info['email']) ? " " : $info['email'];
        $user->major = !isset($info['major']) ? " " : $info['major'];
        $user->college = !isset($info['college']) ? " " : $info['college'];

        // Update the user in the database, then return it to the caller:
        $user->save();
        return $user;
    }
}