<?php
/**
 * ZF2 module definition for the VuFind theme system.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
namespace VuFindTheme;

/**
 * ZF2 module definition for the VuFind theme system.
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
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
            'Zend\Loader\StandardAutoloader' => [
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
                'VuFindTheme\ThemeInfo' => 'VuFindTheme\Module::getThemeInfo',
            ],
            'invokables' => [
                'VuFindTheme\Mobile' => 'VuFindTheme\Mobile',
                'VuFindTheme\ResourceContainer' => 'VuFindTheme\ResourceContainer',
            ],
        ];
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
                'headlink' => 'VuFindTheme\View\Helper\Factory::getHeadLink',
                'headscript' => 'VuFindTheme\View\Helper\Factory::getHeadScript',
                'headthemeresources' =>
                    'VuFindTheme\View\Helper\Factory::getHeadThemeResources',
                'imagelink' => 'VuFindTheme\View\Helper\Factory::getImageLink',
                'inlinescript' =>
                    'VuFindTheme\View\Helper\Factory::getInlineScript',
                'mobileurl' => 'VuFindTheme\View\Helper\Factory::getMobileUrl',
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
        return new ThemeInfo(realpath(__DIR__ . '/../../themes'), 'bootprint3');
    }
}
