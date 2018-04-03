<?php
/**
 * Factory for Alma ILS driver.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien für Sozialwissenschaften 2018.
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
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ILS\Driver;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AlmaFactory implements FactoryInterface
{    
    /**
     * {@inheritDoc}
     * @see \Zend\ServiceManager\Factory\FactoryInterface::__invoke()
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
            // Set up the driver with the date converter (and any extra parameters passed in as options):
            $driver = new $requestedName(
                $container->get('VuFind\Date\Converter'),
                $container->get('VuFind\Config\PluginManager'),
                ...($options ?: [])
            );
            
            // Populate cache storage if a setCacheStorage method is present:
            if (method_exists($driver, 'setCacheStorage')) {
                $driver->setCacheStorage($container->get('VuFind\Cache\Manager')->getCache('object'));
            }
            
            return $driver;
    }
}

?>