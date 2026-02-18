<?php

/**
 * FooterMenu section plugin
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Navigation;

use Symfony\Component\Yaml\Yaml;

use function count;

/**
 * FooterMenu section plugin
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class FooterMenu extends AbstractMenu
{
    /**
     * Constructor
     *
     * @param array $sectionConfig Menu configuration
     * @param array $config        Main configuration
     */
    public function __construct(
        array $sectionConfig,
        array $config
    ) {
        $this->addRequiredSettings(
            [
                'label',
                'MenuItems',
            ],
            self::GROUP_CONTEXT
        );
        $this->addRequiredSettings(
            [
                'label',
                'route',
                'url',
            ],
            self::ITEM_CONTEXT
        );
        $this->addLocalizableSettings(
            [
                'url',
            ],
            self::ITEM_CONTEXT
        );
        parent::__construct($sectionConfig, $config);
    }

    /**
     * Is the setting required?
     *
     * The optional context and context key parameters are used to evaluate if a
     * conditionally required setting is required. If context is omitted returns
     * true for both required and conditionally required settings.
     *
     * @param string               $setting    Setting key
     * @param array<string, mixed> $context    Setting keys and values to be used in evaluation (optional)
     * @param string               $contextKey Key identifying the context (optional)
     *
     * @return bool
     */
    public function isRequiredSetting(
        string $setting,
        array $context = [],
        string $contextKey = self::DEFAULT_CONTEXT
    ): bool {
        if ($contextKey === self::ITEM_CONTEXT) {
            // Conditional requirement checks.
            $diff = array_diff(['route', 'url'], [$setting]);
            if (count($diff) === 1) {
                // Setting is one of the two. If the other setting exists then
                // this setting is optional.
                return count(array_intersect($diff, array_keys($context))) === 0;
            }
        }
        return parent::isRequiredSetting($setting, $context, $contextKey);
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        $yaml = <<<YAML
            footer-left:
              label: footer_header_search_options
              MenuItems:
                - label: 'Search History'
                  route: search-history
            
                - label: 'Advanced Search'
                  route: search-advanced
            
            footer-center:
              label: footer_header_find_more
              MenuItems:
                - label: 'Browse the Catalog'
                  route: browse-home
            
                - label: 'Browse Alphabetically'
                  route: alphabrowse-home
            
                - label: channel_explore
                  route: channels-home
            
                - label: 'Course Reserves'
                  route: search-reserves
            
                - label: 'New Items'
                  route: search-newitem
            
            footer-right:
              label: footer_header_need_help
              MenuItems:
                - label: 'Search Tips'
                  route: help
                  routeParams:
                    topic: search
                  attributes:
                    data-lightbox: data-lightbox
                    class: help-link
            
                - label: 'Ask a Librarian'
                  route: content-page
                  routeParams:
                    page: askLibrary
            
                - label: 'FAQs'
                  route: content-page
                  routeParams:
                    page: faq
            
                - label: 'Cookie Settings'
                  url: '#'
                  checkMethod: checkCookieSettings
                  attributes:
                    data-cc: show-preferencesModal
                    aria-haspopup: dialog
            
                - label: 'Site Map'
                  route: sitemap-home
                  checkMethod: checkSiteMapPage
            YAML;
        return Yaml::parse($yaml);
    }

    /**
     * Check whether to show cookie settings item
     *
     * @return bool
     */
    public function checkCookieSettings(): bool
    {
        return !empty($this->config['Cookies']['consent']);
    }
}
