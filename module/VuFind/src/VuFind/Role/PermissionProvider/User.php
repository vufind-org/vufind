<?php

/**
 * User permission provider for VuFind.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Authorization
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFind\Role\PermissionProvider;

use LmcRbacMvc\Service\AuthorizationService;

use function count;

/**
 * LDAP permission provider for VuFind.
 * based on permission provider Username.php
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class User implements
    PermissionProviderInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Authorization object
     *
     * @var AuthorizationService
     */
    protected $auth;

    /**
     * Constructor
     *
     * @param AuthorizationService $authorization Authorization service
     */
    public function __construct(AuthorizationService $authorization)
    {
        $this->auth = $authorization;
    }

    /**
     * Return an array of roles which may be granted the permission based on
     * the options.
     *
     * @param mixed $options Options provided from configuration.
     *
     * @return array
     */
    public function getPermissions($options)
    {
        // If no user is logged in, or the user doesn't match the passed-in
        // filter, we can't grant the permission to any roles.
        if (!($user = $this->auth->getIdentity())) {
            return [];
        }

        // which user attribute has to match which pattern to get permissions?
        foreach ((array)$options as $option) {
            $parts = explode(' ', $option, 2);
            if (count($parts) < 2) {
                $this->logError("configuration option '{$option}' invalid");
                return [];
            } else {
                [$attribute, $pattern] = $parts;

                // check user attribute values against the pattern
                if (! preg_match('/^\/.*\/$/', $pattern)) {
                    $pattern = '/' . $pattern . '/';
                }

                if (preg_match($pattern, $user[$attribute])) {
                    return ['loggedin'];
                }
            }
        }

        //no matches found, so the user don't get any permissions
        return [];
    }
}
