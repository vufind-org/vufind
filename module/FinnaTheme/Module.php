<?php
/**
 * Module for storing local overrides for VuFindTheme.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
namespace FinnaTheme;

/**
 * Module for storing local overrides for VuFindTheme.
 *
 * @category VuFind
 * @package  Theme
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
class Module
{
    /**
     * Get autoloader configuration
     *
     * @return void
     */
    public function getAutoloaderConfig()
    {
        return [
            'Laminas\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    /**
     * Return service configuration.
     *
     * @return array
     */
    public function getServiceConfig()
    {
        return [
            'factories' => [
                'VuFindTheme\ThemeInfo' => 'FinnaTheme\Module::getThemeInfo',
            ],
        ];
    }

    /**
     * Factory function for ThemeInfo object.
     *
     * @return ThemeInfo
     */
    public static function getThemeInfo()
    {
        return new \VuFindTheme\ThemeInfo(
            realpath(APPLICATION_PATH . '/themes'), 'bootprint3'
        );
    }

    /**
     * Get view helper configuration.
     *
     * @return array
     */
    public function getViewHelperConfig()
    {
        return [
            'factories' => [
                View\Helper\HeadScript::class =>
                    \VuFindTheme\View\Helper\PipelineInjectorFactory::class,
                View\Helper\InlineScript::class =>
                    \VuFindTheme\View\Helper\PipelineInjectorFactory::class,
            ],
            'aliases' => [
                \VuFindTheme\View\Helper\HeadScript::class
                    => View\Helper\HeadScript::class,
                \VuFindTheme\View\Helper\InlineScript::class =>
                    View\Helper\InlineScript::class,
            ],
        ];
    }
}
