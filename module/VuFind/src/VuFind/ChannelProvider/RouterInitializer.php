<?php

/**
 * Channel Provider Router Initializer
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use Laminas\ServiceManager\Initializer\InitializerInterface;
use Psr\Container\ContainerInterface;

/**
 * Channel Provider Router Initializer
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RouterInitializer implements InitializerInterface
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
        if ($instance instanceof AbstractChannelProvider) {
            $instance->setCoverRouter($container->get(\VuFind\Cover\Router::class));
            $instance->setRecordRouter(
                $container->get(\VuFind\Record\Router::class)
            );
        }
        return $instance;
    }
}
