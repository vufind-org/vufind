<?php
/**
 * Abstract FacetCache Factory.
 *
 * PHP version 7
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
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Search\Base;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VuFind\I18n\Locale\LocaleSettings;

/**
 * Abstract FacetCache Factory.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCacheFactory implements FactoryInterface
{
    /**
     * Create a results object.
     *
     * @param ContainerInterface $container Service manager
     * @param string             $name      Name of results object to load (based
     * on name of FacetCache service name)
     *
     * @return Results
     */
    protected function getResults(ContainerInterface $container, $name)
    {
        return $container->get(\VuFind\Search\Results\PluginManager::class)
            ->get($name);
    }

    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $parts = explode('\\', $requestedName);
        $requestedNamespace = $parts[count($parts) - 2];
        $results = $this->getResults($container, $requestedNamespace);
        $cacheManager = $container->get(\VuFind\Cache\Manager::class);
        $language = $container->get(LocaleSettings::class)->getUserLocale();
        return new $requestedName($results, $cacheManager, $language);
    }
}
