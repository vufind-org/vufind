<?php

/**
 * Color Scheme Manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Theme
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Laminas\Stdlib\RequestInterface as Request;
use Psr\Container\ContainerInterface;
use VuFind\Cookie\CookieManager;

use function in_array;

/**
 * Color Scheme Manager
 *
 * @category VuFind
 * @package  Theme
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ColorSchemeManager
{
    /**
     * Supported color schemes for the current theme
     *
     * @var array
     */
    protected $supportedColorSchemes;

    /**
     * Constructor
     *
     * @param array              $config         Theme configuration
     * @param ContainerInterface $serviceManager Top-level service container
     * @param CookieManager      $cookieManager  Cookie manager
     * @param string             $currentTheme   Current theme
     */
    public function __construct(
        protected array $config,
        protected ContainerInterface $serviceManager,
        protected CookieManager $cookieManager,
        protected string $currentTheme,
    ) {
        $rawSupportedColorSchemes = $config['supported_color_schemes'][$currentTheme] ?? '';
        $this->supportedColorSchemes = explode(',', $rawSupportedColorSchemes);
    }

    /**
     * Return the user-selected color scheme, and persist it as a cookie.
     *
     * @param Request $request Request object (for obtaining user parameters);
     * set to null if no request context is available.
     *
     * @return string 'dark' or 'light' if a scheme has been selected, or 'normal'
     * to use the system default
     */
    public function getSelectedColorScheme(?Request $request): string
    {
        // Find out if the user has a saved preference in the POST, URL or cookies:
        $selectedColorScheme = null;
        $saveColorScheme = false;
        $themeColorSchemeKey = $this->currentTheme . '_color_scheme';
        if (isset($request)) {
            $selectedColorScheme = $request->getPost()->get('color_scheme')
                ?? $request->getQuery()->get('color_scheme')
                ?? $request->getCookie()->$themeColorSchemeKey
                ?? 'normal';
            $saveColorScheme = true;
        }

        // Only use the selected color scheme (or system-suggested color scheme) if it's supported
        // by the current theme.
        if (!$this->isSupportedColorScheme($selectedColorScheme)) {
            $selectedColorScheme = $this->supportedColorSchemes[0] ?? 'light';
            $saveColorScheme = false;
        }

        // Save the current setting to a cookie so it persists.
        if ($saveColorScheme) {
            $this->cookieManager->set($themeColorSchemeKey, $selectedColorScheme);
        }

        return $selectedColorScheme;
    }

    /**
     * Make the selected color scheme and the color scheme options available to the view.
     *
     * @param string $selectedColorScheme User-specified color scheme if applicable, or 'normal'
     *
     * @return void
     */
    public function sendColorSchemeInfoToView(string $selectedColorScheme): void
    {
        // Get access to the view model:
        if (PHP_SAPI !== 'cli') {
            $viewModel = $this->serviceManager->get('ViewManager')->getViewModel();

            // Send down the view options:
            $viewModel->setVariable('selectedColorScheme', $selectedColorScheme);
            $viewModel->setVariable('colorSchemeOptions', $this->getColorSchemeOptions());
        }
    }

    /**
     * Return an array of information about user-selectable color schemes. Each
     * entry in the array is an associative array with 'name', 'label' and
     * 'icon' keys.
     *
     * @return array
     */
    protected function getColorSchemeOptions()
    {
        // Load all options from config
        $rawOptions = $this->config['selectable_color_schemes'] ?? [];
        $options = array_map(function ($rawOption) {
            $option = explode(':', $rawOption);
            return [
                'name' => $option[0],
                'label' => $option[1],
                'icon' => $option[2] ?? false,
            ];
        }, $rawOptions);

        // Only return valid options for the current theme
        $options = array_filter($options, function ($option) {
            return $this->isSupportedColorScheme($option['name']);
        });

        return $options;
    }

    /**
     * Determines if the specified color scheme is supported under the current theme.
     *
     * @param string $desiredColorScheme Color scheme name
     *
     * @return bool
     */
    protected function isSupportedColorScheme($desiredColorScheme)
    {
        return in_array($desiredColorScheme, $this->supportedColorSchemes);
    }
}
