<?php

/**
 * LDAP authentication class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */

namespace VuFind\Auth;

use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\Auth as AuthException;

use function in_array;

/**
 * LDAP authentication class
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class LDAP extends AbstractBase
{
    /**
     * Constructor
     *
     * @param ILSAuthenticator $ilsAuthenticator ILS authenticator
     */
    public function __construct(protected ILSAuthenticator $ilsAuthenticator)
    {
    }

    /**
     * Validate configuration parameters. This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        // Check for missing parameters:
        $requiredParams = ['host', 'port', 'basedn', 'username'];
        foreach ($requiredParams as $param) {
            if (
                !isset($this->config->LDAP->$param)
                || empty($this->config->LDAP->$param)
            ) {
                throw new AuthException(
                    'One or more LDAP parameters are missing. Check your config.ini!'
                );
            }
        }
    }

    /**
     * Get the requested configuration setting (or blank string if unset).
     *
     * @param string $name Name of parameter to retrieve.
     *
     * @return string
     */
    protected function getSetting($name)
    {
        $config = $this->getConfig();
        $value = $config->LDAP->$name ?? '';

        // Normalize all values to lowercase except for potentially case-sensitive
        // bind and basedn credentials.
        $doNotLower = ['bind_username', 'bind_password', 'basedn'];
        return (in_array($name, $doNotLower)) ? $value : strtolower($value);
    }

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
        if ($username == '' || $password == '') {
            throw new AuthException('authentication_error_blank');
        }
        return $this->checkLdap($username, $password);
    }

    /**
     * Communicate with LDAP and obtain user details.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @throws AuthException
     * @return UserEntityInterface Object representing logged-in user.
     */
    protected function checkLdap($username, $password)
    {
        // Establish a connection:
        $connection = $this->connect();

        // If necessary, bind in order to perform a search:
        $this->bindForSearch($connection);

        // Search for username
        $info = $this->findUsername($connection, $username);
        if ($info['count']) {
            $data = $this->validateCredentialsInLdap($connection, $info, $password);
            if ($data) {
                return $this->processLDAPUser($username, $data);
            }
        } else {
            $this->debug('user not found');
        }

        throw new AuthException('authentication_error_invalid');
    }

    /**
     * Establish the LDAP connection.
     *
     * @return resource
     */
    protected function connect()
    {
        // Try to connect to LDAP and die if we can't; note that some LDAP setups
        // will successfully return a resource from ldap_connect even if the server
        // is unavailable -- we need to check for bad return values again at search
        // time!
        $host = $this->getSetting('host');
        $port = $this->getSetting('port');
        $this->debug("connecting to host=$host, port=$port");
        $connection = @ldap_connect($host, $port);
        if (!$connection) {
            $this->debug('connection failed');
            throw new AuthException('authentication_error_technical');
        }

        // Set LDAP options -- use protocol version 3
        if (!@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $this->debug('Failed to set protocol version 3');
        }

        // if the host parameter is not specified as ldaps://
        // then (unless TLS is disabled) we need to initiate TLS so we
        // can have a secure connection over the standard LDAP port.
        $disableTls = isset($this->config->LDAP->disable_tls)
            && $this->config->LDAP->disable_tls;
        if (stripos($host, 'ldaps://') === false && !$disableTls) {
            $this->debug('Starting TLS');
            if (!@ldap_start_tls($connection)) {
                $this->debug('TLS failed');
                throw new AuthException('authentication_error_technical');
            }
        }

        return $connection;
    }

    /**
     * If configured, bind an administrative user in order to perform a search
     *
     * @param resource $connection LDAP connection
     *
     * @return void
     */
    protected function bindForSearch($connection)
    {
        // If bind_username and bind_password were supplied in the config file, use
        // them to access LDAP before proceeding. In some LDAP setups, these
        // settings can be excluded in order to skip this step.
        $user = $this->getSetting('bind_username');
        $pass = $this->getSetting('bind_password');
        if ($user != '' && $pass != '') {
            $this->debug("binding as $user");
            $ldapBind = @ldap_bind($connection, $user, $pass);
            if (!$ldapBind) {
                $this->debug('bind failed -- ' . ldap_error($connection));
                throw new AuthException('authentication_error_technical');
            }
        }
    }

    /**
     * Find the specified username in the directory
     *
     * @param resource $connection LDAP connection
     * @param string   $username   Username
     *
     * @return array
     */
    protected function findUsername($connection, $username)
    {
        $ldapFilter = $this->getSetting('username') . '=' . $username;
        $basedn = $this->getSetting('basedn');
        $this->debug("search for $ldapFilter using basedn=$basedn");
        $ldapSearch = @ldap_search($connection, $basedn, $ldapFilter);
        if (!$ldapSearch) {
            $this->debug('search failed -- ' . ldap_error($connection));
            throw new AuthException('authentication_error_technical');
        }

        return ldap_get_entries($connection, $ldapSearch);
    }

    /**
     * Validate credentials
     *
     * @param resource $connection LDAP connection
     * @param array    $info       Data from findUsername()
     * @param string   $password   Password to try
     *
     * @return bool|array Array of user data on success, false otherwise
     */
    protected function validateCredentialsInLdap($connection, $info, $password)
    {
        // Validate the user credentials by attempting to bind to LDAP:
        $dn = $info[0]['dn'];
        $this->debug("binding as $dn");
        $ldapBind = @ldap_bind($connection, $dn, $password);
        if (!$ldapBind) {
            $this->debug('bind failed -- ' . ldap_error($connection));
            return false;
        }
        // If the bind was successful, we can look up the full user info:
        $this->debug('bind successful; reading details');
        $ldapSearch = ldap_read($connection, $dn, 'objectclass=*');
        $data = ldap_get_entries($connection, $ldapSearch);
        if ($data === false) {
            $this->debug('Read failed -- ' . ldap_error($connection));
            throw new AuthException('authentication_error_technical');
        }
        return $data;
    }

    /**
     * Build a User object from details obtained via LDAP.
     *
     * @param string $username Username
     * @param array  $data     Details from ldap_get_entries call.
     *
     * @return UserEntityInterface Object representing logged-in user.
     */
    protected function processLDAPUser($username, $data)
    {
        // Database fields that we may be able to load from LDAP:
        $fields = [
            'firstname', 'lastname', 'email', 'cat_username', 'cat_password',
            'college', 'major',
        ];

        // User object to populate from LDAP:
        $user = $this->getOrCreateUserByUsername($username);

        // Variable to hold catalog password (handled separately from other
        // attributes since we need to pass it to saveUserAndCredentials method to store it):
        $catPassword = null;

        // Loop through LDAP response and map fields to database object based
        // on configuration settings:
        for ($i = 0; $i < $data['count']; $i++) {
            for ($j = 0; $j < $data[$i]['count']; $j++) {
                foreach ($fields as $field) {
                    $configValue = $this->getSetting($field);
                    if ($data[$i][$j] == $configValue && !empty($configValue)) {
                        $value = $data[$i][$configValue];
                        $separator = $this->config->LDAP->separator;
                        // if no separator is given map only the first value
                        if (isset($separator)) {
                            $tmp = [];
                            for ($k = 0; $k < $value['count']; $k++) {
                                $tmp[] = $value[$k];
                            }
                            $value = implode($separator, $tmp);
                        } else {
                            $value = $value[0];
                        }

                        if ($field != 'cat_password') {
                            $this->setUserValueByField($user, $field, $value ?? '');
                        } else {
                            $catPassword = $value;
                        }
                    }
                }
            }
        }

        // Save and return user data:
        $this->saveUserAndCredentials($user, $catPassword, $this->ilsAuthenticator);
        return $user;
    }
}
