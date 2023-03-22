<?php

/**
 * Permission Manager
 *
 * PHP version 7
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */

namespace VuFind\Role;

use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;

/**
 * Permission Manager
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class PermissionManager
{
    use AuthorizationServiceAwareTrait;

    /**
     * List config
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param array $config configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Determine if the user is authorized in a certain context or not
     *
     * @param string $context Context for the permission behavior
     *
     * @return bool
     */
    public function isAuthorized($context)
    {
        $authService = $this->getAuthorizationService();

        // if no authorization service is available return false
        if (!$authService) {
            return false;
        }

        if ($authService->isGranted($context)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a permission rule exists for a given context
     *
     * @param string $context Context for the permission behavior
     *
     * @return bool
     */
    public function permissionRuleExists($context)
    {
        foreach ($this->config as $value) {
            if (!isset($value['permission'])) {
                continue;
            }
            if ($value['permission'] == $context) {
                return true;
            }
            if (
                is_array($value['permission'])
                && in_array($context, $value['permission'])
            ) {
                return true;
            }
        }
        return false;
    }
}
