<?php
/**
 * Username permission provider for VuFind.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role\PermissionProvider;
use ZfcRbac\Service\AuthorizationService;

/**
 * Username permission provider for VuFind.
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Username implements PermissionProviderInterface
{
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
        // whitelist, we can't grant the permission to any roles.
        $user = $this->auth->getIdentity();
        if (!$user || !in_array($user->username, (array)$options)) {
            return [];
        }

        // If we got this far, we can grant the permission to the loggedin role.
        return ['loggedin'];
    }
}
