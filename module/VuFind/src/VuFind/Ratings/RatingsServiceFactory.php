<?php

/**
 * Ratings service factory
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Ratings
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Ratings;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use VuFind\Db\Service\RatingsServiceInterface;
use VuFind\Record\ResourcePopulator;

/**
 * Ratings service
 *
 * @category VuFind
 * @package  Ratings
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 *
 * @codeCoverageIgnore
 */
class RatingsServiceFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Requested service name (unused)
     * @param array              $options   Extra options (unused)
     *
     * @return FavoritesService
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $serviceManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        return new RatingsService(
            $serviceManager->get(RatingsServiceInterface::class),
            $container->get(ResourcePopulator::class)
        );
    }
}
