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

namespace VuFind\Section;

use VuFind\Config\Feature\SettingPropertiesInterface;

/**
 * Interface for a configurable model class representing a section of the UI.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface SectionInterface extends SettingPropertiesInterface
{
    /**
     * Set section key.
     *
     * @param string $key Key
     *
     * @return $this
     */
    public function setKey(string $key): static;

    /**
     * Return section key.
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Set section configuration.
     *
     * @param array $config Configuration
     *
     * @return $this
     */
    public function setConfig(array $config): static;

    /**
     * Return section configuration.
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * Return context variables that can be used to render the section.
     *
     * @return array
     */
    public function getContext(): array;
}
