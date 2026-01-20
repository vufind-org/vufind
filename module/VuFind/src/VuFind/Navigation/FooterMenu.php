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
        protected array $config
    ) {
        parent::__construct($sectionConfig);
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        return [
            'FooterFirst' => [
                'label' => 'footer_header_search_options',
                'MenuItems' => [
                    [
                        'label' => 'Search History',
                        'route' => 'search-history',
                    ],
                    [
                        'label' => 'Advanced Search',
                        'route' => 'search-advanced',
                    ],
                ],
            ],
            'FooterSecond' => [
                'label' => 'footer_header_find_more',
                'MenuItems' => [
                    [
                        'label' => 'Browse the Catalog',
                        'route' => 'browse-home',
                    ],
                    [
                        'label' => 'Browse Alphabetically',
                        'route' => 'alphabrowse-home',
                    ],
                    [
                        'label' => 'channel_explore',
                        'route' => 'channels-home',
                    ],
                    [
                        'label' => 'Course Reserves',
                        'route' => 'search-reserves',
                    ],
                    [
                        'label' => 'New Items',
                        'route' => 'search-newitem',
                    ],
                ],
            ],
            'FooterThird' => [
                'label' => 'footer_header_need_help',
                'MenuItems' => [
                    [
                        'label' => 'Search Tips',
                        'route' => 'help',
                        'routeParams' => [
                            'topic' => 'search',
                        ],
                        'attributes' => [
                            'data-lightbox' => 'data-lightbox',
                            'class' => 'help-link',
                        ],
                    ],
                    [
                        'label' => 'Ask a Librarian',
                        'route' => 'content-page',
                        'routeParams' => [
                            'page' => 'askLibrary',
                        ],
                    ],
                    [
                        'label' => 'FAQs',
                        'route' => 'content-page',
                        'routeParams' => [
                            'page' => 'faq',
                        ],
                    ],
                    [
                        'label' => 'Cookie Settings',
                        'url' => '#',
                        'checkMethod' => 'checkCookieSettings',
                        'attributes' => [
                            'data-cc' => 'show-preferencesModal',
                            'aria-haspopup' => 'dialog',
                        ],
                    ],
                ],
            ],
        ];
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
