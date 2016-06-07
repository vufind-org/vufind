<?php
/**
 * User permission provider for VuFind.
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Role\PermissionProvider;
use ZfcRbac\Service\AuthorizationService;

/**
 * LDAP permission provider for VuFind.
 * based on permission provider Username.php
 *
 * @category VuFind2
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class User implements PermissionProviderInterface
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
        if (!$user) {
            return [];
        }

        $options = is_array($options) ? $options : [$options];
        
        // which user attribute has to match which pattern to get permissions?
        $criteria = [];
        foreach ($options as $option) {
            $attributeValuePair = explode(' ', $option);
            if (count($attributeValuePair) == 2) {
                $criteria[$attributeValuePair[0]] = $attributeValuePair[1];
            } else {
                $this->logError("configuration option '{$option}' invalid");
                return false;
            }
        }
        
        // check user attribute values against the pattern 
        foreach ($criteria as $attribute => $pattern) {
            $subject = $user[$attribute];
            if (preg_match('/' . $pattern . '/', $subject)) {
                return ['loggedin'];
            }
        }
        
        //no matches found, so the user don't get any permissions
        return [];
    }
}
