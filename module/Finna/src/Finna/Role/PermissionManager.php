<?php
/**
 * Permission Manager
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
namespace Finna\Role;

/**
 * Permission Manager
 *
 * @category VuFind
 * @package  Authorization
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class PermissionManager extends \VuFind\Role\PermissionManager
{
    /**
     * Get all active permissions
     *
     * @return array
     */
    public function getActivePermissions()
    {
        $authService = $this->getAuthorizationService();
        $permissions = [];
        foreach ($this->config as $key => $value) {
            if (!isset($value['permission'])) {
                continue;
            }
            if ($authService->isGranted($key)) {
                $permissions = array_merge(
                    $permissions, (array)$value['permission']
                );
            }
        }
        return $permissions;
    }
}
