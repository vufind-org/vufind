<?php
/**
 * Generic permission provider for VuFind.
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
 * @package  Assertions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role;
use Zend\Http\PhpEnvironment\Request;
use ZfcRbac\Service\AuthorizationService;

/**
 * Generic permission provider for VuFind.
 *
 * @category VuFind2
 * @package  Assertions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class PermissionProvider implements PermissionProviderInterface
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Role(s) to modify
     *
     * @var array
     */
    protected $roles;

    /**
     * Permission(s) to grant
     *
     * @var array
     */
    protected $permissions;

    /**
     * Authorization object
     *
     * @var AuthorizationService
     */
    protected $auth;

    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param array                $config        Configuration array indicating
     * which methods of access should be permitted (legal keys = ipRegEx, a
     * regular expression for verifying IP addresses; userWhitelist, a list of
     * legal users)
     * @param string|array         $roles         One or more roles to attach
     * permissions to
     * @param string|array         $permissions   One or more permissions to grant
     * based on the provided configuration
     * @param AuthorizationService $authorization Authorization service
     * @param Request              $request       Request object
     */
    public function __construct(array $config, $roles, $permissions,
        AuthorizationService $authorization, Request $request
    ) {
        $this->config = $config;
        $this->roles = (array)$roles;
        $this->permissions = (array)$permissions;
        $this->auth = $authorization;
        $this->request = $request;
    }

    /**
     * Check if this assertion is true
     *
     * @param AuthorizationService $authorization Authorization service
     * @param mixed                $context       Context variable
     *
     * @return bool
     */
    public function getPermissions()
    {
        // If an IP regex is set, check if the current IP matches.
        if (isset($this->config['ipRegEx'])) {
            $ipMatch = preg_match(
                $this->config['ipRegEx'],
                $this->request->getServer()->get('REMOTE_ADDR')
            );
            if (!$ipMatch) {
                return [];
            }
        }

        // If a user whitelist is set, check if the user is on it.
        if (isset($this->config['userWhitelist'])) {
            $user = $this->auth->getIdentity();
            if (!$user
                || !in_array($user->username, $this->config['userWhitelist'])
            ) {
                return [];
            }
        }

        // If we got this far, there were no failed checks.
        $retVal = [];
        foreach ($this->roles as $role) {
            $retVal[$role] = $this->permissions;
        }
        return $retVal;
    }
}
