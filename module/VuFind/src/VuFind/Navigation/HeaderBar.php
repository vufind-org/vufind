<?php

/**
 * Header bar navigation
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
 * Header bar navigation
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HeaderBar extends AbstractMenu
{
    /**
     * Is feedback enabled?
     *
     * @var bool
     */
    protected bool $feedbackEnabled;

    /**
     * Constructor
     *
     * @param array $sectionConfig       Menu configuration
     * @param array $config              Main configuration
     * @param bool  $cartEnabled         Is cart enabled?
     * @param bool  $accountEnabled      Is account enabled?
     * @param bool  $themeOptionsEnabled Is theme options enabled?
     * @param bool  $allLangsEnabled     Is all langs enabled?
     */
    public function __construct(
        array $sectionConfig,
        array $config,
        protected bool $cartEnabled,
        protected bool $accountEnabled,
        protected bool $themeOptionsEnabled,
        protected bool $allLangsEnabled
    ) {
        parent::__construct($sectionConfig);
        $this->feedbackEnabled = (bool)($config['Feedback']['tab_enabled'] ?? false);
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    public static function getDefaultMenuConfig(): array
    {
        return [
            'Header' => [
                'MenuItems' => [
                    [
                        'label' => 'Feedback',
                        'route' => 'feedback-home',
                        'icon' => 'feedback',
                        'checkMethod' => 'checkFeedback',
                        'attributes' => [
                            'id' => 'feedbackLink',
                            'data-lightbox' => 'data-lightbox',
                        ],
                    ],
                    [
                        'template' => 'Section/HeaderBar/HeaderBar-cart.phtml',
                        'checkMethod' => 'checkCart',
                    ],
                    [
                        'template' => 'Section/HeaderBar/HeaderBar-account.phtml',
                        'checkMethod' => 'checkAccount',
                    ],
                    [
                        'template' => 'Section/HeaderBar/HeaderBar-themeOptions.phtml',
                        'checkMethod' => 'checkThemeOptions',
                    ],
                    [
                        'template' => 'Section/HeaderBar/HeaderBar-allLangs.phtml',
                        'checkMethod' => 'checkAllLangs',
                    ],
                ],
            ],
        ];
    }

    /**
     * Check whether to show feedback item
     *
     * @return bool
     */
    public function checkFeedback(): bool
    {
        return $this->feedbackEnabled;
    }

    /**
     * Check whether to show cart item
     *
     * @return bool
     */
    public function checkCart(): bool
    {
        return $this->cartEnabled;
    }

    /**
     * Check whether to show account item
     *
     * @return bool
     */
    public function checkAccount(): bool
    {
        return $this->accountEnabled;
    }

    /**
     * Check whether to show theme options item
     *
     * @return bool
     */
    public function checkThemeOptions(): bool
    {
        return $this->themeOptionsEnabled;
    }

    /**
     * Check whether to show all languages item
     *
     * @return bool
     */
    public function checkAllLangs(): bool
    {
        return $this->allLangsEnabled;
    }
}
