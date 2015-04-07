<?php
/**
 * LDAP authentication class
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
 * LDAP authentication class
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:authentication_handlers Wiki
 */
class LDAP extends AbstractBase
{
    /**
     * Validate configuration parameters.  This is a support method for getConfig(),
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
            if (!isset($this->config->LDAP->$param)
                || empty($this->config->LDAP->$param)
            ) {
                throw new AuthException(
                    "One or more LDAP parameters are missing. Check your config.ini!"
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
        $value = isset($config->LDAP->$name) ? $config->LDAP->$name : '';

        // Normalize all values to lowercase except for potentially case-sensitive
        // bind and basedn credentials.
        $doNotLower = ['bind_username', 'bind_password', 'basedn'];
        return (in_array($name, $doNotLower)) ? $value : strtolower($value);
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
        return $this->checkLdap($username, $password);
    }

    /**
     * Communicate with LDAP and obtain user details.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
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
        // then we need to initiate TLS so we
        // can have a secure connection over the standard LDAP port.
        if (stripos($host, 'ldaps://') === false) {
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
        // them to access LDAP before proceeding.  In some LDAP setups, these
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
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    protected function processLDAPUser($username, $data)
    {
        // Database fields that we may be able to load from LDAP:
        $fields = [
            'firstname', 'lastname', 'email', 'cat_username', 'cat_password',
            'college', 'major'
        ];

        // User object to populate from LDAP:
        $user = $this->getUserTable()->getByUsername($username);

        // Variable to hold catalog password (handled separately from other
        // attributes since we need to use saveCredentials method to store it):
        $catPassword = null;

        // Loop through LDAP response and map fields to database object based
        // on configuration settings:
        for ($i = 0; $i < $data["count"]; $i++) {
            for ($j = 0; $j < $data[$i]["count"]; $j++) {
                foreach ($fields as $field) {
                    $configValue = $this->getSetting($field);
                    if ($data[$i][$j] == $configValue && !empty($configValue)) {
                        $value = $data[$i][$configValue][0];
                        $this->debug("found $field = $value");
                        if ($field != "cat_password") {
                            $user->$field = $value;
                        } else {
                            $catPassword = $value;
                        }
                    }
                }
            }
        }

        // Save credentials if applicable:
        if (!empty($catPassword) && !empty($user->cat_username)) {
            $user->saveCredentials($user->cat_username, $catPassword);
        }

        // Update the user in the database, then return it to the caller:
        $user->save();
        return $user;
    }
}