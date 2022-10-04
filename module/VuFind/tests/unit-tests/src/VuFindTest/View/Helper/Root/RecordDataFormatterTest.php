<?php
/**
 * RecordDataFormatter Test Class
 *
 * PHP version 7
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

use Psr\Container\ContainerInterface;
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
class RecordDataFormatterTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get a mock record router.
     *
     * @return \VuFind\Record\Router
     */
    protected function getMockRecordRouter()
    {
        $mock = $this->getMockBuilder(\VuFind\Record\Router::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getActionRouteDetails'])
            ->getMock();
        $mock->expects($this->any())->method('getActionRouteDetails')
            ->will($this->returnValue(['route' => 'home', 'params' => []]));
        return $mock;
    }

    /**
     * Get view helpers needed by test.
     *
     * @param ContainerInterface $container Mock service container
     *
     * @return array
     */
    protected function getViewHelpers($container)
    {
        $context = new \VuFind\View\Helper\Root\Context();
        return [
            'auth' => new \VuFind\View\Helper\Root\Auth(
                $this->getMockBuilder(\VuFind\Auth\Manager::class)->disableOriginalConstructor()->getMock(),
                $this->getMockBuilder(\VuFind\Auth\ILSAuthenticator::class)->disableOriginalConstructor()->getMock()
            ),
            'context' => $context,
            'config' => new \VuFind\View\Helper\Root\Config($container->get(\VuFind\Config\PluginManager::class)),
            'doi' => new \VuFind\View\Helper\Root\Doi($context),
            'icon' => new \VuFind\View\Helper\Root\Icon(
                [],
                new \Laminas\Cache\Storage\Adapter\BlackHole(),
                new \Laminas\View\Helper\EscapeHtmlAttr(),
                new \Laminas\View\Helper\HeadLink()
            ),
            'openUrl' => new \VuFind\View\Helper\Root\OpenUrl($context, [], $this->getMockBuilder(\VuFind\Resolver\Driver\PluginManager::class)->disableOriginalConstructor()->getMock()),
            'proxyUrl' => new \VuFind\View\Helper\Root\ProxyUrl(),
            'record' => new \VuFind\View\Helper\Root\Record(),
            'recordLinker' => new \VuFind\View\Helper\Root\RecordLinker($this->getMockRecordRouter()),
            'searchOptions' => new \VuFind\View\Helper\Root\SearchOptions(new \VuFind\Search\Options\PluginManager($container)),
            'searchTabs' => $this->getMockBuilder(\VuFind\View\Helper\Root\SearchTabs::class)->disableOriginalConstructor()->getMock(),
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
        $methods = [
            'getBuildings', 'getDeduplicatedAuthors', 'getContainerTitle', 'getTags'
        ];
        $record = $this->getMockBuilder(\VuFind\RecordDriver\SolrDefault::class)
            ->onlyMethods($methods)
            ->getMock();
        $record->expects($this->any())->method('getTags')
            ->will($this->returnValue([]));
        // Force a return value of zero so we can test this edge case value (even
        // though in the context of "building"/"container title" it makes no sense):
        $record->expects($this->any())->method('getBuildings')
            ->will($this->returnValue(['0']));
        $record->expects($this->any())->method('getContainerTitle')
            ->will($this->returnValue('0'));
        // Expect only one call to getDeduplicatedAuthors to confirm that caching
        // works correctly (we need this data more than once, but should only pull
        // it from the driver once).
        $authors = [
            'primary' => ['Vico, Giambattista, 1668-1744.' => []],
            'secondary' => ['Pandolfi, Claudia.' => []],
        ];
        $record->expects($this->once())->method('getDeduplicatedAuthors')
            ->will($this->returnValue($authors));

        // Load record data from fixture file:
        $fixture = $this->getJsonFixture('misc/testbug2.json');
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
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(
            \VuFind\Config\PluginManager::class,
            new \VuFind\Config\PluginManager($container)
        );
        $container->set(
            \VuFind\Config\PathResolver::class,
            new \VuFind\Config\PathResolver()
        );
        $formatter = $factory($container, RecordDataFormatter::class);

        // Create a view object with a set of helpers:
        $helpers = $this->getViewHelpers($container);
        $view = $this->getPhpRenderer($helpers);

        // Mock out the router to avoid errors:
        $match = new \Laminas\Router\RouteMatch([]);
        $match->setMatchedRouteName('foo');
        $view->plugin('url')
            ->setRouter($this->createMock(\Laminas\Router\RouteStackInterface::class))
            ->setRouteMatch($match);

        // Inject the view object into all of the helpers:
        $formatter->setView($view);
        foreach ($helpers as $helper) {
            $helper->setView($view);
        }

        return $formatter;
    }

    /**
     * Find a result in the results array.
     *
     * @param string $needle   Result to look up.
     * @param array  $haystack Result set.
     *
     * @return mixed
     */
    protected function findResult($needle, $haystack)
    {
        foreach ($haystack as $current) {
            if ($current['label'] == $needle) {
                return $current;
            }
        }
        return null;
    }

    /**
     * Extract labels from a results array.
     *
     * @param array $results Results to process.
     *
     * @return array
     */
    protected function getLabels($results)
    {
        $callback = function ($c) {
            return $c['label'];
        };
        return array_map($callback, $results);
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
            'dataMethod' => 'getBuildings', 'pos' => 0, 'context' => ['foo' => 1],
            'translationTextDomain' => 'prefix_',
        ];
        $spec['MultiTest'] = [
            'dataMethod' => 'getFormats',
            'renderType' => 'Multi',
            'pos' => 1000,
            'multiFunction' => function ($data) {
                return [
                    [
                        'label' => 'Multi Data',
                        'values' => $data,
                    ],
                    [
                        'label' => 'Multi Count',
                        'values' => count($data),
                    ],
                ];
            }
        ];
        $spec['MultiEmptyArrayTest'] = [
            'dataMethod' => true,
            'renderType' => 'Multi',
            'pos' => 2000,
            'multiFunction' => function () {
                return [];
            }
        ];
        $spec['MultiNullTest'] = [
            'dataMethod' => true,
            'renderType' => 'Multi',
            'pos' => 2000,
            'multiFunction' => function () {
                return null;
            }
        ];
        $spec['MultiNullInArrayWithZeroTest'] = [
            'dataMethod' => true,
            'renderType' => 'Multi',
            'pos' => 2000,
            'allowZero' => false,
            'multiFunction' => function () {
                return [
                    [
                        'label' => 'Null',
                        'values' => null,
                    ],
                    [
                        'label' => 'ZeroBlocked',
                        'values' => 0,
                    ]
                ];
            }
        ];
        $spec['MultiNullInArrayWithZeroAllowedTest'] = [
            'dataMethod' => true,
            'renderType' => 'Multi',
            'pos' => 2000,
            'allowZero' => true,
            'multiFunction' => function () {
                return [
                    [
                        'label' => 'Null',
                        'values' => null,
                    ],
                    [
                        'label' => 'ZeroAllowed',
                        'values' => 0,
                    ]
                ];
            }
        ];
        $spec['MultiWithSortPos'] = [
            'dataMethod' => true,
            'renderType' => 'Multi',
            'pos' => 0,
            'multiFunction' => function () {
                return [
                    [
                        'label' => 'b',
                        'values' => 'b',
                        'options' => ['pos' => 3000]
                    ],
                    [
                        'label' => 'a',
                        'values' => 'a',
                        'options' => ['pos' => 3000]
                    ],
                    [
                        'label' => 'c',
                        'values' => 'c',
                        'options' => ['pos' => 2999]
                    ],
                ];
            }
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
            'Multi Count' => 1,
            'Multi Data' => 'Book',
            'Subjects' => 'Naples (Kingdom) History Spanish rule, 1442-1707 Sources',
            'Online Access' => 'http://fictional.com/sample/url',
            // Double slash at the end comes from inline javascript
            'Tags' => 'Add Tag No Tags, Be the first to tag this record! //',
            'ZeroAllowed' => 0,
            'c' => 'c',
            'a' => 'a',
            'b' => 'b',
        ];
        $driver = $this->getDriver();
        $results = $formatter->getData($driver, $spec);

        // Check for expected array keys
        $this->assertEquals(array_keys($expected), $this->getLabels($results));

        // Check for expected text (with markup stripped)
        foreach ($expected as $key => $value) {
            $this->assertEquals(
                $value,
                trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        strip_tags($this->findResult($key, $results)['value'])
                    )
                )
            );
        }

        // Check for exact markup in representative example:
        $this->assertEquals(
            '<span property="availableLanguage" typeof="Language"><span property="name">Italian</span></span><br /><span property="availableLanguage" typeof="Language"><span property="name">Latin</span></span>',
            $this->findResult('Language', $results)['value']
        );

        // Check for context in Building:
        $this->assertEquals(
            ['foo' => 1],
            $this->findResult('Building', $results)['context']
        );
    }
}
