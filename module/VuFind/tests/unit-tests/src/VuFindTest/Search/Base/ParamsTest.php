<?php
/**
 * Base Search Object Parameters Test
 *
 * PHP version 7
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
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Search\Base;

use VuFind\Config\PluginManager;
use VuFind\Search\Base\Options;
use VuFind\Search\Base\Params;

/**
 * Base Search Object Parameters Test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ParamsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock configuration plugin manager
     *
     * @return PluginManager
     */
    protected function getMockConfigManager()
    {
        return $this->getMockBuilder(PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock Options object
     *
     * @param PluginManager $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getMockOptions($configManager = null)
    {
        return $this->getMockForAbstractClass(
            Options::class,
            [$configManager ?? $this->getMockConfigManager()]
        );
    }

    /**
     * Get mock Params object
     *
     * @param Options       $options       Options object to send to Params
     * constructor (null for new mock)
     * @param PluginManager $configManager Config manager for Params object (null
     * for new mock)
     *
     * @return Params
     */
    protected function getMockParams($options = null, $configManager = null)
    {
        $configManager = $configManager ?? $this->getMockConfigManager();
        return $this->getMockForAbstractClass(
            Params::class,
            [$options ?? $this->getMockOptions($configManager), $configManager]
        );
    }

    /**
     * Test that getFacetLabel works as expected.
     *
     * @return void
     */
    public function testGetFacetLabel()
    {
        $params = $this->getMockParams();
        // If we haven't set up any facets yet, labels will be unrecognized:
        $this->assertEquals('unrecognized_facet_label', $params->getFacetLabel('foo'));

        // Now if we add a facet, we should get the label back:
        $params->addFacet('foo', 'foo_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId()
    {
        $this->assertEquals('Mock', $this->getMockParams()->getSearchClassId());
    }

    /**
     * Test that spelling replacement works as expected.
     *
     * @return void
     */
    public function testSpellingReplacements()
    {
        $params = $this->getMockParams();

        // Key test: word boundaries:
        $params->setBasicSearch('go good googler');
        $this->assertEquals(
            'run good googler',
            $params->getDisplayQueryWithReplacedTerm('go', 'run')
        );

        // Key test: replacement of wildcard queries:
        $params->setBasicSearch('oftamologie*');
        $this->assertEquals(
            'ophtalmologie*',
            $params->getDisplayQueryWithReplacedTerm(
                'oftamologie*',
                'ophtalmologie*'
            )
        );
    }
}
