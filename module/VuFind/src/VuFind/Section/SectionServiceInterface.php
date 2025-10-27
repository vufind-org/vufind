<?php

/**
 * Section service interface.
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
 * Section service interface.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface SectionServiceInterface
{
    public const DEFAULT_CONFIG_FILE = 'Sections.yaml';

    /**
     * Get section.
     *
     * @param string       $key    Section key in configuration
     * @param array|string $config Configuration or configuration file name
     *                             (optional)
     *
     * @return ?SectionInterface
     */
    public function getSection(
        string $key,
        array|string $config = self::DEFAULT_CONFIG_FILE
    ): ?SectionInterface;

    /**
     * Localize settings of the provided section.
     *
     * If settings are not provided, calls SectionInterface::getConfig() to get
     * the configuration to be localized.
     *
     * @param SectionInterface $section    Section
     * @param ?array           $settings   Settings to localize (optional)
     * @param string           $contextKey Key identifying the context (optional)
     * @param bool             $useFirst   Use first array item if item matching
     *                                     locale(s) was not found (optional)
     *
     * @return array
     */
    public function localizeSettings(
        SectionInterface $section,
        ?array $settings = null,
        string $contextKey = SettingPropertiesInterface::DEFAULT_CONTEXT,
        bool $useFirst = true
    ): array;
}
