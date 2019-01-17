<?php
/**
 * ZF2 module definition for the VuFind theme system.
 *
 * PHP version 7
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

use Zend\ServiceManager\Factory\InvokableFactory;

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
                MixinGenerator::class => ThemeInfoInjectorFactory::class,
                Mobile::class => InvokableFactory::class,
                ResourceContainer::class => InvokableFactory::class,
                ThemeCompiler::class => ThemeInfoInjectorFactory::class,
                ThemeGenerator::class => ThemeInfoInjectorFactory::class,
                ThemeInfo::class => ThemeInfoFactory::class,
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
                'VuFindTheme\View\Helper\HeadThemeResources' =>
                    'VuFindTheme\View\Helper\Factory::getHeadThemeResources',
                'VuFindTheme\View\Helper\ImageLink' =>
                    'VuFindTheme\View\Helper\Factory::getImageLink',
                'Zend\View\Helper\HeadLink' =>
                    'VuFindTheme\View\Helper\Factory::getHeadLink',
                'Zend\View\Helper\HeadScript' =>
                    'VuFindTheme\View\Helper\Factory::getHeadScript',
                'Zend\View\Helper\InlineScript' =>
                    'VuFindTheme\View\Helper\Factory::getInlineScript',
            ],
            'aliases' => [
                'headThemeResources' => 'VuFindTheme\View\Helper\HeadThemeResources',
                'imageLink' => 'VuFindTheme\View\Helper\ImageLink',
            ],
        ];
    }
}
