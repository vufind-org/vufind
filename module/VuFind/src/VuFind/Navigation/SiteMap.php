<?php

/**
 * SiteMap section plugin
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
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

use VuFind\Exception\BadConfig;

use function array_key_exists;
use function in_array;
use function is_array;

/**
 * SiteMap section plugin
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SiteMap extends AbstractMenu
{
    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string $setting    Setting
     * @param array  $context    Settings to be used in evaluation (optional)
     * @param string $contextKey Key identifying the context (optional)
     *
     * @return bool
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        if (
            $contextKey === self::GROUP_CONTEXT
            && array_key_exists('section', $context)
        ) {
            // If a section setting exists, no other settings are required.
            return false;
        }
        return parent::isRequiredSetting($setting, $context, $contextKey);
    }

    /**
     * Process and filter groups.
     *
     * @param array $groups Groups to process and filter
     *
     * @return array
     */
    protected function processGroups(array $groups): array
    {
        $availableGroups = parent::processGroups($groups);
        return $this->processGroupSectionKey($availableGroups);
    }

    /**
     * Process or filter group.
     *
     * @param array $group Group to process
     *
     * @return array|false Processed group or false if group should be filtered
     */
    protected function processGroup(array $group): array|false
    {
        // Groups containing a section key will be processed in a separate step.
        if (isset($group['section'])) {
            return $group;
        }
        return parent::processGroup($group);
    }

    /**
     * Process any groups with a section key.
     *
     * @param array $groups Groups
     *
     * @return array
     * @throws BadConfig
     */
    protected function processGroupSectionKey(array $groups): array
    {
        $processedGroups = [];
        foreach ($groups as $groupName => $group) {
            if (!$section = $group['section'] ?? false) {
                $processedGroups[$groupName] = $group;
                continue;
            }
            if (is_array($section)) {
                $groups = $section['groups'] ?? [];
                $section = $section['section'];
            } else {
                $groups = [];
            }
            $plugin = $this->sectionService->getSection($section);
            if ($plugin instanceof SiteMap) {
                throw new BadConfig('Specifying SiteMap plugin sections is not possible');
            }
            foreach ($plugin->getMenu() as $pluginGroupName => $pluginGroup) {
                if (!empty($groups) && !in_array($pluginGroupName, $groups)) {
                    continue;
                }
                if (isset($processedGroups[$pluginGroupName])) {
                    throw new BadConfig('Group key clash in configuration');
                }
                $processedGroups[$pluginGroupName] = $pluginGroup;
            }
        }
        return $processedGroups;
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        return [
            'HomePage' => [
                'MenuItems' => [
                    [
                        'label' => 'Home Page',
                        'route' => 'home',
                    ],
                ],
            ],
            'HeaderBar' => [
                'section' => 'HeaderBar',
            ],
            'FooterMenu' => [
                'section' => 'FooterMenu',
            ],
        ];
    }
}
