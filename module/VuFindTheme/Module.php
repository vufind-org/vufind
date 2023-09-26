<?php

/**
 * Module definition for the VuFind theme system.
 *
 * PHP version 8
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
     * Return generic configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'vufind' => [
                // VuFind's theme system overrides the default Laminas template
                // loading behavior based on template name prefixes, which usually
                // correspond to module names. By default, VuFind will apply the
                // theme system to all loaded modules. If you need to apply theming
                // to a controller whose namespace does not directly correspond to a
                // loaded module, you will need to add it as a prefix in
                // extra_theme_prefixes (e.g. 'MyNamespace/'). Conversely, if you
                // are loading a Laminas module that includes templates and does not
                // follow VuFind's theme conventions, you should add that module name
                // as a prefix in excluded_theme_prefixes to allow the default
                // behavior to take effect.
                //
                // By default, VuFind assumes that any modules loaded from the
                // Laminas ecosystem use default Laminas template inflection, and
                // all other modules follow VuFind conventions. If you need different
                // behavior, just override the below settings in your local module's
                // module.config.php configuration.
                'excluded_theme_prefixes' => ['Laminas'],
                'extra_theme_prefixes' => [],
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
                InjectTemplateListener::class =>
                    InjectTemplateListenerFactory::class,
                MixinGenerator::class => ThemeInfoInjectorFactory::class,
                Mobile::class => InvokableFactory::class,
                ResourceContainer::class => InvokableFactory::class,
                ThemeCompiler::class => ThemeInfoInjectorFactory::class,
                ThemeGenerator::class => ThemeGeneratorFactory::class,
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
                View\Helper\FootScript::class =>
                    View\Helper\PipelineInjectorFactory::class,
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
                View\Helper\SetupThemeResources::class =>
                    View\Helper\SetupThemeResourcesFactory::class,
            ],
            'aliases' => [
                'footScript' => View\Helper\FootScript::class,
                // Legacy alias for compatibility with pre-8.0 templates:
                'headThemeResources' => View\Helper\SetupThemeResources::class,
                'imageLink' => View\Helper\ImageLink::class,
                \Laminas\View\Helper\HeadLink::class => View\Helper\HeadLink::class,
                \Laminas\View\Helper\HeadScript::class =>
                    View\Helper\HeadScript::class,
                \Laminas\View\Helper\InlineScript::class =>
                    View\Helper\InlineScript::class,
                'parentTemplate' => View\Helper\ParentTemplate::class,
                'slot' => View\Helper\Slot::class,
                'templatePath' => View\Helper\TemplatePath::class,
                'setupThemeResources' => View\Helper\SetupThemeResources::class,
            ],
        ];
    }
}
