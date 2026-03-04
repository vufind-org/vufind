<?php

/**
 * Action initializer
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Action;

use Laminas\ServiceManager\Initializer\InitializerInterface;
use Psr\Container\ContainerInterface;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Http\RouteHelper;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Action initializer
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ActionInitializer implements InitializerInterface
{
    /**
     * Given an instance and a Service Manager, initialize the instance.
     *
     * @param ContainerInterface $container Service manager
     * @param object             $instance  Instance to initialize
     *
     * @return object
     */
    public function __invoke(ContainerInterface $container, $instance)
    {
        if ($instance instanceof ActionInterface) {
            $instance->setRouteHelper($container->get(RouteHelper::class))
                ->setHelperPluginManager($container->get(HelperPluginManager::class));
            if ($instance instanceof AbstractTemplateRenderingAction) {
                $instance->setTemplateRenderer($container->get(TemplateRendererInterface::class));
            }
        }
        return $instance;
    }
}
