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
    protected function getMockConfigManager(): PluginManager
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
    protected function getMockOptions(PluginManager $configManager = null): Options
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
    protected function getMockParams(
        ?Options $options = null,
        ?PluginManager $configManager = null
    ): Params {
        $configManager = $configManager ?? $this->getMockConfigManager();
        return $this->getMockForAbstractClass(
            Params::class,
            [$options ?? $this->getMockOptions($configManager), $configManager]
        );
    }

    /**
     * Test that getCheckboxFacets works as expected.
     *
     * @return void
     */
    public function testGetCheckboxFacets(): void
    {
        // None by default:
        $params = $this->getMockParams();
        $this->assertEquals([], $params->getCheckboxFacets());

        // Adding one works:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals(
            [
                [
                    'desc' => 'checkbox_label',
                    'filter' => 'foo:bar',
                    'selected' => false,
                    'alwaysVisible' => false
                ]
            ],
            $params->getCheckboxFacets()
        );

        // Selecting one works:
        $params->addFilter('foo:bar');
        $this->assertEquals(
            [
                [
                    'desc' => 'checkbox_label',
                    'filter' => 'foo:bar',
                    'selected' => true,
                    'alwaysVisible' => false
                ]
            ],
            $params->getCheckboxFacets()
        );
    }

    /**
     * Test that getFacetLabel works as expected.
     *
     * @return void
     */
    public function testGetFacetLabel(): void
    {
        $params = $this->getMockParams();
        // If we haven't set up any facets yet, labels will be unrecognized:
        $this->assertEquals('unrecognized_facet_label', $params->getFacetLabel('foo'));

        // Now if we add a facet, we should get the label back:
        $params->addFacet('foo', 'foo_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));

        // If we add a checkbox facet for a field that already has an assigned label,
        // we do not expect the checkbox label to override the field label:
        $params->addCheckboxFacet('foo:bar', 'checkbox_label');
        $this->assertEquals('foo_label', $params->getFacetLabel('foo', 'bar'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo', 'baz'));
        $this->assertEquals('foo_label', $params->getFacetLabel('foo'));
    }

    /**
     * Test that we get a mock search class ID while testing.
     *
     * @return void
     */
    public function testGetSearchClassId(): void
    {
        $this->assertEquals('Mock', $this->getMockParams()->getSearchClassId());
    }

    /**
     * Test that spelling replacement works as expected.
     *
     * @return void
     */
    public function testSpellingReplacements(): void
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
