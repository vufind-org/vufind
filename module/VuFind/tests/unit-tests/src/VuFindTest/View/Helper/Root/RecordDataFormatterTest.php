<?php
/**
 * RecordDataFormatter Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
namespace VuFindTest\View\Helper\Root;
use VuFind\View\Helper\Root\RecordDataFormatter;
use VuFind\View\Helper\Root\RecordDataFormatterFactory;

/**
 * RecordDataFormatter Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordDataFormatterTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Setup test case.
     *
     * Mark test skipped if short_open_tag is not enabled. The partial
     * uses short open tags. This directive is PHP_INI_PERDIR,
     * i.e. can only be changed via php.ini or a per-directory
     * equivalent. The test will fail if the test is run on
     * a system with short_open_tag disabled in the system-wide php
     * ini-file.
     *
     * @return void
     */
    protected function setup()
    {
        parent::setup();
        if (!ini_get('short_open_tag')) {
            $this->markTestSkipped('Test requires short_open_tag to be enabled');
        }
    }

    /**
     * Get view helpers needed by test.
     *
     * @return array
     */
    protected function getViewHelpers()
    {
        $context = new \VuFind\View\Helper\Root\Context();
        return [
            'auth' => new \VuFind\View\Helper\Root\Auth($this->getMockBuilder('VuFind\Auth\Manager')->disableOriginalConstructor()->getMock()),
            'context' => $context,
            'openUrl' => new \VuFind\View\Helper\Root\OpenUrl($context, [], $this->getMockBuilder('VuFind\Resolver\Driver\PluginManager')->disableOriginalConstructor()->getMock()),
            'proxyUrl' => new \VuFind\View\Helper\Root\ProxyUrl(),
            'record' => new \VuFind\View\Helper\Root\Record(),
            'recordLink' => new \VuFind\View\Helper\Root\RecordLink($this->getMockBuilder('VuFind\Record\Router')->disableOriginalConstructor()->getMock()),
            'searchTabs' => $this->getMockBuilder('VuFind\View\Helper\Root\SearchTabs')->disableOriginalConstructor()->getMock(),
            'transEsc' => new \VuFind\View\Helper\Root\TransEsc(),
            'translate' => new \VuFind\View\Helper\Root\Translate(),
            'usertags' => new \VuFind\View\Helper\Root\UserTags(),
        ];
    }

    /**
     * Get a record driver with fake data.
     *
     * @param array $overrides Fixture fields to override.
     *
     * @return SolrDefault
     */
    protected function getDriver($overrides = [])
    {
        // "Mock out" tag functionality to avoid database access:
        $record = $this->getMockBuilder('VuFind\RecordDriver\SolrDefault')
            ->setMethods(['getBuilding', 'getContainerTitle', 'getTags'])
            ->getMock();
        $record->expects($this->any())->method('getTags')
            ->will($this->returnValue([]));
        // Force a return value of zero so we can test this edge case value (even
        // though in the context of "building"/"container title" it makes no sense):
        $record->expects($this->any())->method('getBuilding')
            ->will($this->returnValue(0));
        $record->expects($this->any())->method('getContainerTitle')
            ->will($this->returnValue('0'));

        // Load record data from fixture file:
        $fixture = json_decode(
            file_get_contents(
                realpath(
                    VUFIND_PHPUNIT_MODULE_PATH . '/fixtures/misc/testbug2.json'
                )
            ),
            true
        );
        $record->setRawData($overrides + $fixture['response']['docs'][0]);
        return $record;
    }

    /**
     * Build a formatter, including necessary mock view w/ helpers.
     *
     * @return RecordDataFormatter
     */
    protected function getFormatter()
    {
        // Build the formatter:
        $factory = new RecordDataFormatterFactory();
        $formatter = $factory->__invoke();

        // Create a view object with a set of helpers:
        $helpers = $this->getViewHelpers();
        $view = $this->getPhpRenderer($helpers);

        // Mock out the router to avoid errors:
        $match = new \Zend\Mvc\Router\RouteMatch([]);
        $match->setMatchedRouteName('foo');
        $view->plugin('url')
            ->setRouter($this->createMock('Zend\Mvc\Router\RouteStackInterface'))
            ->setRouteMatch($match);

        // Inject the view object into all of the helpers:
        $formatter->setView($view);
        foreach ($helpers as $helper) {
            $helper->setView($view);
        }

        return $formatter;
    }

    /**
     * Test citation generation
     *
     * @return void
     */
    public function testFormatting()
    {
        $formatter = $this->getFormatter();
        $spec = $formatter->getDefaults('core');
        $spec['Building'] = [
            'dataMethod' => 'getBuilding', 'pos' => 0, 'context' => ['foo' => 1],
            'translationTextDomain' => 'prefix_'
        ];

        $expected = [
            'Building' => 'prefix_0',
            'Published in' => '0',
            'Main Author' => 'Vico, Giambattista, 1668-1744.',
            'Other Authors' => 'Pandolfi, Claudia.',
            'Format' => 'Book',
            'Language' => 'ItalianLatin',
            'Published' => 'Centro di Studi Vichiani, 1992',
            'Edition' => 'Fictional edition.',
            'Series' => 'Vico, Giambattista, 1668-1744. Works. 1982 ;',
            'Subjects' => 'Naples (Kingdom) History Spanish rule, 1442-1707 Sources',
            'Online Access' => 'http://fictional.com/sample/url',
            'Tags' => 'Add Tag No Tags, Be the first to tag this record!',
        ];
        $driver = $this->getDriver();
        $results = $formatter->getData($driver, $spec);

        // Check for expected array keys
        $this->assertEquals(array_keys($expected), array_keys($results));

        // Check for expected text (with markup stripped)
        foreach ($expected as $key => $value) {
            $this->assertEquals(
                $value,
                trim(preg_replace('/\s+/', ' ', strip_tags($results[$key]['value'])))
            );
        }

        // Check for exact markup in representative example:
        $this->assertEquals('Italian<br />Latin', $results['Language']['value']);

        // Check for context in Building:
        $this->assertEquals(['foo' => 1], $results['Building']['context']);
    }
}
