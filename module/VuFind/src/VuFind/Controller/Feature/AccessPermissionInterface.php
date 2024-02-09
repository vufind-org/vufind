<?php

/**
 * Access Permission Interface -- provides getters and setters for permission setting.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

/**
 * Access Permission Interface -- provides getters and setters for permission setting.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface AccessPermissionInterface
{
    /**
     * Getter for access permission (string for required permission name, false
     * for no permission required, null to use default permission).
     *
     * @return string|bool|null
     */
    public function getAccessPermission();

    /**
     * Getter for access permission.
     *
     * @param string|false $ap Permission to require for access to the controller (false
     * for no requirement)
     *
     * @return void
     */
    public function setAccessPermission($ap);
}
