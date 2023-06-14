<?php

/**
 * Eds autocomplete test class.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Autocomplete;

use VuFind\Autocomplete\Eds;
use VuFindSearch\Backend\EDS\Backend;

/**
 * Eds autocomplete test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <jochen.lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class EdsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\SearchServiceTrait;

    /**
     * Get a mock backend
     *
     * @return Backend
     */
    protected function getMockBackend()
    {
        return $this->getMockBuilder(\VuFindSearch\Backend\EDS\Backend::class)
            ->onlyMethods(['autocomplete'])
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * Wrap a mock backend in a backend manager
     *
     * @param Backend $backend Backend to wrap
     *
     * @return \VuFind\Search\BackendManager
     */
    protected function getMockBackendManager(Backend $backend)
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set('EDS', $backend);
        return new \VuFind\Search\BackendManager($container);
    }

    /**
     * Test getSuggestions.
     *
     * @return void
     */
    public function testGetSuggestions()
    {
        $backend = $this->getMockBackend();
        $manager = $this->getMockBackendManager($backend);
        $eds = new Eds($this->getSearchService($manager));
        $backend->expects($this->once())
            ->method('autocomplete')
            ->with($this->equalTo('query'), $this->equalTo('rawqueries'))
            ->will($this->returnValue([1, 2, 3]));
        $this->assertEquals([1, 2, 3], $eds->getSuggestions('query'));
    }

    /**
     * Test getSuggestions with non-default configuration.
     *
     * @return void
     */
    public function testGetSuggestionsWithNonDefaultConfiguration()
    {
        $backend = $this->getMockBackend();
        $manager = $this->getMockBackendManager($backend);
        $eds = new Eds($this->getSearchService($manager));
        $eds->setConfig('holdings');
        $backend->expects($this->once())
            ->method('autocomplete')
            ->with($this->equalTo('query'), $this->equalTo('holdings'))
            ->will($this->returnValue([4, 5]));
        $this->assertEquals([4, 5], $eds->getSuggestions('query'));
    }
}
