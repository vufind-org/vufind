<?php
/**
 * Alma Database authentication class
 *
 * PHP version 5
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

use VuFind\Exception\Auth as AuthException;
use Zend\Crypt\Password\Bcrypt;

class AlmaDatabase extends Database
{
    
    /**
     * ILS Authenticator
     *
     * @var object
     */
    protected $authenticator;
    
    /**
     * Catalog connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $catalog = null;
    
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
     * @param \VuFind\ILS\Connection $connection
     * @param \VuFind\Auth\ILSAuthenticator $authenticator
     */
    public function __construct(\VuFind\ILS\Connection $connection, \VuFind\Auth\ILSAuthenticator $authenticator)
    {
        $this->catalog = $connection;
        $this->authenticator = $authenticator;
        $this->almaDriver = $connection->getDriver();
        $this->almaConfig = $connection->getDriverConfig();
    }
    
    /**
     * Create a new user account in Alma AND in the VuFind Database.
     * 
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing new account details.
     * @return NULL|\VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        // User variable
        $user = null;
        
        // Ensure that all expected parameters are populated to avoid notices
        // in the code below.
        $params = [
            'firstname' => '', 'lastname' => '', 'username' => '',
            'password' => '', 'password2' => '', 'email' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }
        
        // Validate Input
        $this->validateUsernameAndPassword($params);
        
        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('authentication_error_creation_blocked');
        }
        
        // Make sure we have a unique username
        $table = $this->getUserTable();
        if ($table->getByUsername($params['username'], false)) {
            throw new AuthException('That username is already taken');
        }
        // Make sure we have a unique email
        if ($table->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }
        
        // Create user account in Alma
        $almaAnswer = $this->almaDriver->createAlmaUser($params);
        
        // Create user account in VuFind user table if Alma gave us an answer
        if ($almaAnswer != null) {
            // If we got this far, we're ready to create the account:
            $user = $table->createRowForUsername($params['username']);
            $user->firstname = $params['firstname'];
            $user->lastname = $params['lastname'];
            $user->email = $params['email'];
            if ($this->passwordHashingEnabled()) {
                $bcrypt = new Bcrypt();
                $user->pass_hash = $bcrypt->create($params['password']);
            } else {
                $user->password = $params['password'];
            }
            // Save the Alma primary ID as cat_id to the VuFind user table
            $user->cat_id = (isset($almaAnswer->primary_id)) ? $almaAnswer->primary_id : null;
            
            // Save the new user to the user table
            $user->save();
            
            // Save the credentials to cat_username and cat_password to bypass the ILS login screen from VuFind
            $user->saveCredentials($params['username'], $params['password']);
        } else {
            throw new AuthException('Error while creating user in Alma');
        }

        return $user;
    }
}

?>