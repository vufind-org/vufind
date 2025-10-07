<?php

/**
 * Trait for tests that need a mock or simulated VuFind\Search objects.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results;
use VuFind\Search\Results\PluginManager;

/**
 * Trait for tests that need a mock or simulated VuFind\Search objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait SearchObjectsTrait
{
    /**
     * Get an instance of an anonymous class extending the Base Options object
     *
     * @param ?ConfigManagerInterface $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getBaseOptionsObject(?ConfigManagerInterface $configManager = null): Options
    {
        return new class ($configManager ?? $this->createMock(ConfigManagerInterface::class)) extends Options {
            /**
             * Return the route name for the search results action.
             *
             * @return string
             */
            public function getSearchAction()
            {
                return '';
            }

            /**
             * Get the identifier used for naming the various search classes in this family.
             *
             * @return string
             */
            public function getSearchClassId()
            {
                return 'Mock';
            }
        };
    }

    /**
     * Get mock search options.
     *
     * @param string $subNamespace The sub-namespace of \VuFind\Search for the object being mocked
     *
     * @return MockObject&Options
     */
    protected function getMockOptions(string $subNamespace = 'Solr'): MockObject&Options
    {
        return $this->createMock("VuFind\Search\\$subNamespace\Options");
    }

    /**
     * Get mock search params.
     *
     * @param ?Options $options      The search options contained within the search parameters
     * @param string   $subNamespace The sub-namespace of \VuFind\Search for the object being mocked
     *
     * @return MockObject&Params
     */
    protected function getMockParams(?Options $options = null, string $subNamespace = 'Solr'): MockObject&Params
    {
        $params = $this->createMock("VuFind\Search\\$subNamespace\Params");
        $params->method('getOptions')->willReturn($options ?? $this->getMockOptions($subNamespace));
        return $params;
    }

    /**
     * Get mock results plugin object.
     *
     * @param ?Params $params       The search params contained within the search results
     * @param string  $subNamespace The sub-namespace of \VuFind\Search for the object being mocked
     *
     * @return MockObject&Results
     */
    protected function getMockResults(?Params $params = null, string $subNamespace = 'Solr'): MockObject&Results
    {
        $results = $this->createMock("VuFind\Search\\$subNamespace\Results");
        $params ??= $this->getMockParams(null, $subNamespace);
        $results->method('getParams')->willReturn($params);
        $results->method('getOptions')->willReturn($params->getOptions());
        return $results;
    }

    /**
     * Get mock results plugin manager.
     *
     * @param array $map                  Map of service name => object to return
     * @param bool  $allowDefaultFallback Should we return a default mock for services undefined in the map?
     *
     * @return MockObject&PluginManager
     */
    protected function getMockResultsPluginManager(
        array $map = [],
        bool $allowDefaultFallback = false
    ): MockObject&PluginManager {
        $rpm = $this->createMock(PluginManager::class);
        $rpm->method('get')->willReturnCallback(
            function ($service) use ($map, $allowDefaultFallback) {
                if (!$allowDefaultFallback && !isset($map[$service])) {
                    throw new \Exception("Unknown service: $service");
                }
                return $map[$service] ?? $this->getMockResults();
            }
        );
        return $rpm;
    }
}
