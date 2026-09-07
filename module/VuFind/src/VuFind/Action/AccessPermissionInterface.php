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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action;

/**
 * Access Permission Interface -- provides getters and setters for permission setting.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface AccessPermissionInterface
{
    /**
     * Get access permission.
     *
     * @return string|false|null
     *
     * @see AbstractAction::$accessPermission
     */
    public function getAccessPermission(): string|false|null;

    /**
     * Set access permission.
     *
     * @param string|false|null $permission Permission to require
     *
     * @return static
     *
     * @see AbstractAction::$accessPermission
     */
    public function setAccessPermission(string|false|null $permission): static;

    /**
     * Get access denied behavior.
     *
     * @return ?string
     *
     * @see AbstractAction::$accessDeniedBehavior
     */
    public function getAccessDeniedBehavior(): ?string;

    /**
     * Set access denied behavior.
     *
     * @param ?string $behavior Access denied behavior
     *
     * @return static
     *
     * @see AbstractAction::$accessDeniedBehavior
     */
    public function setAccessDeniedBehavior(?string $behavior): static;
}
