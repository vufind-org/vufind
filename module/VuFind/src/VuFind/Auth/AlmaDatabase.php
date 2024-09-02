<?php

/**
 * Alma Database authentication class
 *
 * PHP version 8
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
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
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

/**
 * Authentication class for Alma. The VuFind database and the Alma API are
 * combined for authentication by this classe.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class AlmaDatabase extends Database
{
    /**
     * Alma driver
     *
     * @var \VuFind\ILS\Driver\Alma
     */
    protected $almaDriver = null;

    /**
     * Alma config
     *
     * @var array
     */
    protected $almaConfig = null;

    /**
     * Constructor
     *
     * @param \VuFind\ILS\Connection        $catalog       The ILS connection
     * @param \VuFind\Auth\ILSAuthenticator $authenticator The ILS authenticator
     */
    public function __construct(
        protected \VuFind\ILS\Connection $catalog,
        protected \VuFind\Auth\ILSAuthenticator $authenticator
    ) {
        $this->almaDriver = $catalog->getDriver();
        $this->almaConfig = $catalog->getDriverConfig();
    }

    /**
     * Create a new user account in Alma AND in the VuFind Database.
     *
     * @param Request $request Request object containing new account details.
     *
     * @return UserEntityInterface New user entity.
     */
    public function create($request)
    {
        // When in privacy mode, don't create an Alma account and delegate
        // further code execution to the parent.
        if ($this->getConfig()->Authentication->privacy) {
            return parent::create($request);
        }

        // Collect POST parameters from request
        $params = $this->collectParamsFromRequest($request);

        // Validate username and password
        $this->validateUsername($params);
        $this->validatePassword($params);

        // Get the user service
        $userService = $this->getUserService();

        // Make sure parameters are correct
        $this->validateParams($params, $userService);

        // Create user account in Alma
        $almaAnswer = $this->almaDriver->createAlmaUser($params);

        // Create user account in VuFind user table if Alma gave us an answer
        if ($almaAnswer !== null) {
            // If we got this far, we're ready to create the account:
            $user = $this->createUserFromParams($params, $userService);

            // Add the Alma primary ID as cat_id to the VuFind user table
            $user->setCatId($almaAnswer->primary_id ?? null);

            // Save the new user to the user table
            $this->getUserService()->persistEntity($user);

            // Save the credentials to cat_username and cat_password to bypass
            // the ILS login screen from VuFind
            $this->authenticator->saveUserCatalogCredentials($user, $params['username'], $params['password']);
        } else {
            throw new AuthException($this->translate('ils_account_create_error'));
        }

        return $user;
    }
}
