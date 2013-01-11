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
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * ILS authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
class ILS extends AbstractBase
{
    protected $catalog = null;

    /**
     * Set the ILS connection for this object.
     *
     * @param \VuFind\ILS\Connection $connection ILS connection to set
     */
    public function __construct(\VuFind\ILS\Connection $connection)
    {
        $this->setCatalog($connection);
    }

    /**
     * Get the ILS driver associated with this object (or load the default from
     * the service manager.
     *
     * @return \VuFind\ILS\Driver\DriverInterface
     */
    public function getCatalog()
    {
        return $this->catalog;
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
        $this->catalog = $connection;
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
        $config = $this->getConfig();
        $usernameField = isset($config->Authentication->ILS_username_field)
            ? $config->Authentication->ILS_username_field : 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new AuthException('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $user = $this->getUserTable()->getByUsername($info[$usernameField]);

        // No need to store the ILS password in VuFind's main password field:
        $user->password = "";

        // Update user information based on ILS data:
        $user->firstname = !isset($info['firstname']) ? " " : $info['firstname'];
        $user->lastname = !isset($info['lastname']) ? " " : $info['lastname'];
        $user->email = !isset($info['email']) ? " " : $info['email'];
        $user->major = !isset($info['major']) ? " " : $info['major'];
        $user->college = !isset($info['college']) ? " " : $info['college'];

        // Update the user in the database, then return it to the caller:
        $user->saveCredentials(
            !isset($info['cat_username']) ? " " : $info['cat_username'],
            !isset($info['cat_password']) ? " " : $info['cat_password']
        );

        return $user;
    }
}