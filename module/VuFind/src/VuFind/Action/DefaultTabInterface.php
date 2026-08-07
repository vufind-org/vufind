<?php

/**
 * Default tab Interface -- provides getters and setters for default tab.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action;

/**
 * Default tab Interface -- provides getters and setters for default tab.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
interface DefaultTabInterface
{
    /**
     * Get default tab.
     *
     * @return ?string
     */
    public function getDefaultTab(): ?string;

    /**
     * Set default tab.
     *
     * @param ?string $tab Default tab
     *
     * @return static
     */
    public function setDefaultTab(?string $tab): static;

    /**
     * Get fallback default tab.
     *
     * @return ?string
     */
    public function getFallbackDefaultTab(): ?string;

    /**
     * Set fallback default tab.
     *
     * @param ?string $tab Fallback default tab
     *
     * @return static
     */
    public function setFallbackDefaultTab(?string $tab): static;
}
