<?php
/**
 * Module definition for the VuFind theme system.
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

use Laminas\Mvc\View\Http\InjectTemplateListener as ParentInjectTemplateListener;
use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Module definition for the VuFind theme system.
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
            'aliases' => [
                ParentInjectTemplateListener::class => InjectTemplateListener::class,
            ],
            'factories' => [
                InjectTemplateListener::class => InvokableFactory::class,
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
                View\Helper\HeadThemeResources::class =>
                    View\Helper\HeadThemeResourcesFactory::class,
                View\Helper\ImageLink::class => View\Helper\ImageLinkFactory::class,
                View\Helper\HeadLink::class =>
                    View\Helper\PipelineInjectorFactory::class,
                View\Helper\HeadScript::class =>
                    View\Helper\PipelineInjectorFactory::class,
                View\Helper\ParentTemplate::class =>
                    View\Helper\ParentTemplateFactory::class,
                View\Helper\InlineScript::class =>
                    View\Helper\PipelineInjectorFactory::class,
                View\Helper\Slot::class =>
                    View\Helper\PipelineInjectorFactory::class,
                View\Helper\TemplatePath::class =>
                    View\Helper\TemplatePathFactory::class,
            ],
            'aliases' => [
                'headThemeResources' => View\Helper\HeadThemeResources::class,
                'imageLink' => View\Helper\ImageLink::class,
                \Laminas\View\Helper\HeadLink::class => View\Helper\HeadLink::class,
                \Laminas\View\Helper\HeadScript::class =>
                    View\Helper\HeadScript::class,
                \Laminas\View\Helper\InlineScript::class =>
                    View\Helper\InlineScript::class,
                'parentTemplate' => View\Helper\ParentTemplate::class,
                'slot' => View\Helper\Slot::class,
                'templatePath' => View\Helper\TemplatePath::class,
            ],
        ];
    }
}
