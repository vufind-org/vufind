<?php

/**
 * Section trait.
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

use VuFind\Config\Feature\ConfigSettingPropertiesTrait;
use VuFind\Section\PageRegion;

/**
 * Section trait.
 *
 * @category VuFind
 * @package  Section
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait SectionTrait
{
    use ConfigSettingPropertiesTrait;

    /**
     * Section key in configuration.
     *
     * @var string
     */
    protected string $sectionKey;

    /**
     * Section configuration.
     *
     * @var array
     */
    protected array $sectionConfig;

    /**
     * Set section configuration.
     *
     * @param array $sectionConfig Section configuration
     *
     * @return $this
     */
    public function setSectionConfig(array $sectionConfig): static
    {
        $this->sectionConfig = $this->validateSettings($sectionConfig);
        return $this;
    }

    /**
     * Return section configuration.
     *
     * @return array
     */
    public function getSectionConfig(): array
    {
        return $this->sectionConfig;
    }

    /**
     * Set section key.
     *
     * @param string $sectionKey Section key
     *
     * @return $this
     */
    public function setSectionKey(string $sectionKey): static
    {
        $this->sectionKey = $sectionKey;
        return $this;
    }

    /**
     * Return section key.
     *
     * @return string
     */
    public function getSectionKey(): string
    {
        return $this->sectionKey;
    }

    /**
     * Is the section active?
     *
     * An inactive section does not need to be rendered. An active section may
     * be rendered, but may still not be visible.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return true;
    }

    /**
     * Return context variables that can be used to render the section.
     *
     * @param ?PageRegion $pageRegion Page region in which the section is used (optional)
     *
     * @return ?array Context variables, or null if section should not be rendered
     */
    public function getSectionContext(?PageRegion $pageRegion = null): ?array
    {
        if (!$this->isActive()) {
            return null;
        }
        return $this->sectionConfig;
    }

    /**
     * Get available items from a given list.
     *
     * @param array $list Items to filter
     *
     * @return array
     */
    protected function filterAvailable(array $list): array
    {
        return array_filter(
            $list,
            function ($item) {
                if (!isset($item['checkMethod'])) {
                    return true;
                }
                if ('alwaysFail' === $item['checkMethod']) {
                    return false;
                }
                return $this->{$item['checkMethod']}();
            }
        );
    }
}
