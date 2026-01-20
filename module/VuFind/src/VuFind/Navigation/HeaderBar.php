<?php

/**
 * HeaderBar section plugin
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

use Laminas\Http\Request;
use Laminas\View\Model\ViewModel;
use VuFind\Auth\Manager;
use VuFind\Cart;
use VuFind\I18n\Locale\LocaleSettings;

use function array_key_exists;
use function count;

/**
 * HeaderBar section plugin
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
     * Constructor
     *
     * @param array          $sectionConfig  Menu configuration
     * @param array          $config         Main configuration
     * @param Cart           $cart           Cart
     * @param Manager        $authManager    Authentication manager
     * @param ViewModel      $viewModel      View model
     * @param LocaleSettings $localeSettings Locale settings
     * @param Request        $request        Request
     */
    public function __construct(
        array $sectionConfig,
        protected array $config,
        protected Cart $cart,
        protected Manager $authManager,
        protected ViewModel $viewModel,
        protected LocaleSettings $localeSettings,
        protected Request $request
    ) {
        $this->addRequiredSettings(
            [
                'MenuItems',
            ],
            self::GROUP_CONTEXT
        );
        $this->addRequiredSettings(
            [
                'label',
                'route',
                'url',
                'template',
            ],
            self::ITEM_CONTEXT
        );
        $this->addLocalizableSettings(
            [
                'url',
            ],
            self::ITEM_CONTEXT
        );
        parent::__construct($sectionConfig);
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
            $diff = array_diff(['route', 'url', 'template'], [$setting]);
            if (count($diff) === 2) {
                // Setting is one of the three. If one of the two other settings
                // exists then this setting is optional.
                return count(array_intersect($diff, array_keys($context))) === 0;
            }
            if ($setting === 'label' && array_key_exists('template', $context)) {
                // Label is not required when a template setting exists.
                return false;
            }
        }
        return parent::isRequiredSetting($setting, $context, $contextKey);
    }

    /**
     * Return context variables that can be used to render the section.
     *
     * @return array
     */
    public function getSectionContext(): array
    {
        $context = parent::getSectionContext();
        $context['userLang'] = $this->localeSettings->getUserLocale();
        $context['allLangs'] = $this->localeSettings->getEnabledLocales();
        $context['requestUri'] = $this->request->getRequestUri();
        return $context;
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
        return (bool)($this->config['Feedback']['tab_enabled'] ?? false);
    }

    /**
     * Check whether to show cart item
     *
     * @return bool
     */
    public function checkCart(): bool
    {
        return $this->cart->isActive();
    }

    /**
     * Check whether to show account item
     *
     * @return bool
     */
    public function checkAccount(): bool
    {
        return $this->authManager->loginEnabled();
    }

    /**
     * Check whether to show theme options item
     *
     * @return bool
     */
    public function checkThemeOptions(): bool
    {
        return ($options = $this->viewModel->getVariable('themeOptions'))
            && (count($options) > 1);
    }

    /**
     * Check whether to show all languages item
     *
     * @return bool
     */
    public function checkAllLangs(): bool
    {
        return count($this->localeSettings->getEnabledLocales()) > 1;
    }
}
