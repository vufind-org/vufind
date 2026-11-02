<?php

/**
 * Interface for a configurable model class representing a section of the UI.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Section\Plugin;

use VuFind\Config\Feature\ConfigSettingPropertiesInterface;

/**
 * Interface for a configurable model class representing a section of the UI.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface SectionInterface extends ConfigSettingPropertiesInterface
{
    /**
     * Set section key.
     *
     * @param string $sectionKey Section key
     *
     * @return $this
     */
    public function setSectionKey(string $sectionKey): static;

    /**
     * Return section key.
     *
     * @return string
     */
    public function getSectionKey(): string;

    /**
     * Set section configuration.
     *
     * @param array $sectionConfig Section configuration
     *
     * @return $this
     */
    public function setSectionConfig(array $sectionConfig): static;

    /**
     * Return section configuration.
     *
     * @return array
     */
    public function getSectionConfig(): array;

    /**
     * Return context variables that can be used to render the section.
     *
     * @return array
     */
    public function getSectionContext(): array;
}
