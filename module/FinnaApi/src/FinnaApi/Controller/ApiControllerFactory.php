<?php

/**
 * Factory for ApiController.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2021.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace FinnaApi\Controller;

use Psr\Container\ContainerInterface;

/**
 * Factory for ApiController.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ApiControllerFactory extends \VuFindApi\Controller\ApiControllerFactory
{
    /**
     * Get the API controllers to register with ApiController
     *
     * @param ContainerInterface $container Service manager
     *
     * @return array
     */
    protected function getApiControllersToRegister(ContainerInterface $container)
    {
        $config = $container->get('Config');
        return array_diff(
            $config['vufind_api']['register_controllers'],
            [
                \VuFindApi\Controller\Search2ApiController::class,
                \VuFindApi\Controller\WebApiController::class,
            ]
        );
    }
}
