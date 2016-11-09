<?php

/**
 * UrlQueryHelper unit tests.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
namespace VuFindTest\Search;

use VuFind\Search\UrlQueryHelper;
use VuFind\Search\Factory\UrlQueryHelperFactory;
use VuFindTest\Unit\TestCase as TestCase;
use VuFindSearch\Query\Query;

/**
 * UrlQueryHelper unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UrlQueryHelperTest extends TestCase
{
    /**
     * Test the basic functionality of the helper.
     *
     * @return void
     */
    public function testBasicFunctionality()
    {
        // Test basic getters
        $query = new Query('search');
        $helper = new UrlQueryHelper(['foo' => 'bar'], $query);
        $this->assertEquals('?foo=bar&amp;lookfor=search', $helper->getParams());
        $this->assertEquals('?foo=bar&amp;lookfor=search', (string)$helper);
        $this->assertEquals(
            ['foo' => 'bar', 'lookfor' => 'search'], $helper->getParamArray()
        );
        $this->assertEquals(
            '<input type="hidden" name="foo" value="bar" />',
            $helper->asHiddenFields(['lookfor' => '/.*/'])
        );

        // Test setDefaultParameters and disabling escaping
        $this->assertEquals(
            '?foo=baz&lookfor=search',
            $helper->setDefaultParameter('foo', 'baz')->getParams(false)
        );

        // Test query suppression
        $this->assertEquals(false, $helper->isQuerySuppressed());
        $helper->setSuppressQuery(true);
        $this->assertEquals(true, $helper->isQuerySuppressed());
        $this->assertEquals('?foo=baz', $helper->getParams());
        $helper->setSuppressQuery(false);
        $this->assertEquals(false, $helper->isQuerySuppressed());
        $this->assertEquals('?foo=baz&lookfor=search', $helper->getParams(false));

        // Test replacing query terms
        $this->assertEquals(
            '?foo=baz&amp;lookfor=srch',
            $helper->replaceTerm('search', 'srch')->getParams()
        );
        $this->assertEquals(
            '?foo=baz&amp;lookfor=srch',
            $helper->setSearchTerms('srch')->getParams()
        );

        // Test adding/removing facets and filters
        $faceted = $helper->addFacet('f', '1')->addFilter('f:2');
        $this->assertEquals(
            '?foo=baz&lookfor=search&filter%5B%5D=f%3A%221%22&filter%5B%5D=f%3A2',
            $faceted->getParams(false)
        );
        $this->assertEquals(
            '?foo=baz&lookfor=search&filter%5B%5D=f%3A%221%22',
            $faceted->removeFacet('f', '2')->getParams(false)
        );
        $this->assertEquals(
            '?foo=baz&lookfor=search&filter%5B%5D=f%3A2',
            $faceted->removeFilter('f:1')->getParams(false)
        );
        $this->assertEquals(
            '?foo=baz&lookfor=search',
            $faceted->removeAllFilters()->getParams(false)
        );

        // Test stacking setters
        $this->assertEquals(
            '?foo=baz&sort=title&view=grid&lookfor=search&type=x&limit=50&page=3',
            $helper->setSort('title')->setViewParam('grid')->setHandler('x')
                ->setLimit(50)->setPage(3)->getParams(false)
        );
    }

    /**
     * Test advanced search param building.
     *
     * @return void
     */
    public function testAdvancedSearch()
    {
        $fixturePath = realpath(__DIR__ . '/../../../../fixtures/searches') . '/advanced/';
        $q = unserialize(file_get_contents($fixturePath . 'query'));
        $helper = new UrlQueryHelper([], $q);
        $this->assertEquals(
            '?join=OR&bool0%5B%5D=AND&lookfor0%5B%5D=oranges&lookfor0%5B%5D=bananas'
            . '&lookfor0%5B%5D=pears&type0%5B%5D=CallNumber&type0%5B%5D=toc'
            . '&type0%5B%5D=ISN&bool1%5B%5D=OR&lookfor1%5B%5D=cars'
            . '&lookfor1%5B%5D=trucks&type1%5B%5D=Title&type1%5B%5D=Subject'
            . '&bool2%5B%5D=NOT&lookfor2%5B%5D=squid&type2%5B%5D=AllFields',
            $helper->getParams(false)
        );
    }

    /**
     * Test that the factory does its job properly.
     *
     * @return void
     */
    public function testFactory()
    {
        $factory = new UrlQueryHelperFactory();
        $config = $this->getMock('VuFind\Config\PluginManager');
        $params = new \VuFindTest\Search\TestHarness\Params(
            new \VuFindTest\Search\TestHarness\Options($config), $config
        );
        $params->setBasicSearch('foo', 'bar');
        $params->setLimit(100);
        $params->setPage(5);
        $params->setView('grid');
        $helper = $factory->fromParams($params);
        $this->assertEquals(
            '?limit=100&view=grid&page=5&lookfor=foo&type=bar',
            $helper->getParams(false)
        );
    }
}
