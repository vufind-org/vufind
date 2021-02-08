<?php

/**
 * Mix-in for constructing Solr search objects for tests.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Unit;

use VuFind\Search\Solr\Options;
use VuFind\Search\Solr\Params;
use VuFind\Search\Solr\Results;

/**
 * Mix-in for constructing Solr search objects for tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait SolrSearchObjectTrait
{
    /**
     * Get mock config manager.
     *
     * @return \VuFind\Config\PluginManager
     */
    public function getMockConfigManager()
    {
        $manager = $this->createMock(\VuFind\Config\PluginManager::class);
        $manager->expects($this->any())->method('get')
            ->will($this->returnValue(new \Laminas\Config\Config([])));
        return $manager;
    }

    /**
     * Get Solr options.
     *
     * @return Options
     */
    public function getSolrOptions()
    {
        return new Options(
            $this->getMockConfigManager()
        );
    }

    /**
     * Get Solr params.
     *
     * @return Params
     */
    public function getSolrParams()
    {
        return new Params(
            $this->getSolrOptions(),
            $this->getMockConfigManager()
        );
    }

    /**
     * Get Solr results.
     *
     * @return Results;
     */
    public function getSolrResults()
    {
        return new Results(
            $this->getSolrParams(),
            $this->createMock(\VuFindSearch\Service::class),
            $this->createMock(\VuFind\Record\Loader::class)
        );
    }
}
