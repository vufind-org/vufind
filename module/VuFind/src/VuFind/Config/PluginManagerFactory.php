<?php
/**
 * Plugin Manager factory.
 *
 * PHP version 5
 *
 * Copyright (C) 2018 Villanova University,
 *               2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Plugin Manager factory.
 *
 * Deprecated as {@see \VuFind\Config\Manager} should be used instead
 * of {@see \VuFind\Config\PluginManager}.
 *
 * @category   VuFind
 * @package    Config
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @author     Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development Wiki
 * @deprecated File deprecated since X.0.0
 */
class PluginManagerFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container Service manager
     * @param string             $requestedName Service being created
     * @param array|null         $options Extra options (optional)
     *
     * @return object|PluginManager
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        return new PluginManager($container);
    }
}
