<?php

/**
 * Finna Markdown Service factory
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @package  VuFind\Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Service;

use Finna\CommonMark\Extension\CustomElementExtension;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use Psr\Container\ContainerInterface;

/**
 * Finna Markdown Service factory
 *
 * @category VuFind
 * @package  VuFind\Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MarkdownFactory extends \VuFind\Service\MarkdownFactory
{
    /**
     * Service Manager
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service Manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        $this->container = $container;
        return parent::__invoke($container, $requestedName, $options);
    }

    /**
     * Get Markdown environment.
     *
     * @return EnvironmentBuilderInterface
     */
    protected function getEnvironment(): EnvironmentBuilderInterface
    {
        $environment = parent::getEnvironment();

        $config = $this->container->get('config');
        $customElementConfig
            = $config['vufind']['plugin_managers']['view_customelement'] ?? [];
        $elements = array_keys($customElementConfig['aliases'] ?? []);
        if (!empty($elements)) {
            $environment->addExtension(
                new CustomElementExtension(
                    $elements,
                    $this->container->get('ViewHelperManager')->get('customElement')
                )
            );
        }

        return $environment;
    }
}
