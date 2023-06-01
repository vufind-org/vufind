<?php

/**
 * UrlQueryHelper unit tests.
 *
 * PHP version 8
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

use VuFind\Search\Factory\UrlQueryHelperFactory;
use VuFind\Search\UrlQueryHelper;
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
class UrlQueryHelperTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get a preconfigured helper.
     *
     * @param array $request Request parameters
     * @param Query $query   Query object
     *
     * @return UrlQueryHelper
     */
    protected function getHelper($request = ['foo' => 'bar'], $query = null)
    {
        return new UrlQueryHelper($request, $query ?? new Query('search'));
    }

    /**
     * Test the basic functionality of the helper.
     *
     * @return void
     */
    public function testBasicGetters()
    {
        // Test basic getters
        $helper = $this->getHelper();
        $this->assertEquals('?foo=bar&amp;lookfor=search', $helper->getParams());
        $this->assertEquals('?foo=bar&amp;lookfor=search', (string)$helper);
        $this->assertEquals(
            ['foo' => 'bar', 'lookfor' => 'search'],
            $helper->getParamArray()
        );
        $this->assertEquals(
            '<input type="hidden" name="foo" value="bar">',
            $helper->asHiddenFields(['lookfor' => '/.*/'])
        );
    }

    /**
     * Test the behavior of setDefaultParameters
     *
     * @return void
     */
    public function testSetDefaultParameters()
    {
        $helper = $this->getHelper();

        // Test setDefaultParameters and disabling escaping. Note that, because
        // the "foo" parameter is already set in the original request, adding a
        // default does NOT override the existing value.
        $this->assertEquals(
            '?foo=bar&lookfor=search',
            $helper->setDefaultParameter('foo', 'baz', false)->getParams(false)
        );
        // Now let's add a default parameter that was NOT part of the incoming
        // request... we DO want this to get added to the query:
        $this->assertEquals(
            '?foo=bar&lookfor=search&xyzzy=true',
            $helper->setDefaultParameter('xyzzy', 'true', false)->getParams(false)
        );
        // Finally, let's force an override of an existing parameter:
        $this->assertEquals(
            '?foo=baz&lookfor=search&xyzzy=true',
            $helper->setDefaultParameter('foo', 'baz', true)->getParams(false)
        );

        // Confirm that we can look up a list of configured parameters:
        $this->assertEquals(
            ['foo', 'xyzzy'],
            $helper->getParamsWithConfiguredDefaults()
        );
    }

    /**
     * Test query suppression.
     *
     * @return void
     */
    public function testQuerySuppression()
    {
        $helper = $this->getHelper();
        $this->assertEquals(false, $helper->isQuerySuppressed());
        $helper->setSuppressQuery(true);
        $this->assertEquals(true, $helper->isQuerySuppressed());
        $this->assertEquals('?foo=bar', $helper->getParams());
        $helper->setSuppressQuery(false);
        $this->assertEquals(false, $helper->isQuerySuppressed());
        $this->assertEquals('?foo=bar&lookfor=search', $helper->getParams(false));
    }

    /**
     * Test replacing query terms
     *
     * @return void
     */
    public function testReplacingQueryTerms()
    {
        $helper = $this->getHelper();
        $this->assertEquals(
            '?foo=bar&amp;lookfor=srch',
            $helper->replaceTerm('search', 'srch')->getParams()
        );
        $this->assertEquals(
            '?foo=bar&amp;lookfor=srch',
            $helper->setSearchTerms('srch')->getParams()
        );
    }

    /**
     * Test adding/removing facets and filters
     *
     * @return void
     */
    public function testFacetsAndFilters()
    {
        $helper = $this->getHelper();
        $faceted = $helper->addFacet('f', '1')->addFilter('f:2');
        $this->assertEquals(
            '?foo=bar&lookfor=search&filter%5B%5D=f%3A%221%22&filter%5B%5D=f%3A2',
            $faceted->getParams(false)
        );
        $this->assertEquals(
            '?foo=bar&lookfor=search&filter%5B%5D=f%3A%221%22',
            $faceted->removeFacet('f', '2')->getParams(false)
        );
        $this->assertEquals(
            '?foo=bar&lookfor=search&filter%5B%5D=f%3A2',
            $faceted->removeFilter('f:1')->getParams(false)
        );
        $this->assertEquals(
            '?foo=bar&lookfor=search',
            $faceted->removeAllFilters()->getParams(false)
        );
    }

    /**
     * Test stacking setters
     *
     * @return void
     */
    public function testStackingSetters()
    {
        $helper = $this->getHelper();
        $this->assertEquals(
            '?foo=bar&sort=title&view=grid&lookfor=search&type=x&limit=50&page=3',
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
        $q = unserialize($this->getFixture('searches/advanced/query'));
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
        $config = $this->createMock(\VuFind\Config\PluginManager::class);
        $params = new \VuFindTest\Search\TestHarness\Params(
            new \VuFindTest\Search\TestHarness\Options($config),
            $config
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
