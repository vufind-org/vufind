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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindTheme;
use Zend\ServiceManager\ServiceManager;

/**
 * ZF2 module definition for the VuFind theme system.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
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
                'VuFindTheme\MixinGenerator' =>
                    'VuFindTheme\Module::getMixinGenerator',
                'VuFindTheme\ThemeCompiler' =>
                    'VuFindTheme\Module::getThemeCompiler',
                'VuFindTheme\ThemeGenerator' =>
                    'VuFindTheme\Module::getThemeGenerator',
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
     * Factory function for MixinGenerator object.
     *
     * @param ServiceManager $sm Service manager
     *
     * @return MixinGenerator
     */
    public static function getMixinGenerator(ServiceManager $sm)
    {
        return new MixinGenerator($sm->get('VuFindTheme\ThemeInfo'));
    }

    /**
     * Factory function for ThemeCompiler object.
     *
     * @param ServiceManager $sm Service manager
     *
     * @return ThemeCompiler
     */
    public static function getThemeCompiler(ServiceManager $sm)
    {
        return new ThemeCompiler($sm->get('VuFindTheme\ThemeInfo'));
    }

    /**
     * Factory function for ThemeGenerator object.
     *
     * @param ServiceManager $sm Service manager
     *
     * @return ThemeGenerator
     */
    public static function getThemeGenerator(ServiceManager $sm)
    {
        return new ThemeGenerator($sm->get('VuFindTheme\ThemeInfo'));
    }

    /**
     * Factory function for ThemeInfo object.
     *
     * @return ThemeInfo
     */
    public static function getThemeInfo()
    {
        return new ThemeInfo(realpath(APPLICATION_PATH . '/themes'), 'bootprint3');
    }
}
