<?php

/**
 * ScheduledSearch/Notify command test.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\ScheduledSearch;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\ScheduledSearch\NotifyCommand;
use VuFindTest\Container\MockContainer;

/**
 * ScheduledSearch/Notify command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class NotifyCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Container for building mocks.
     *
     * @var MockContainer
     */
    protected $container;

    /**
     * Setup method
     *
     * @return void
     */
    public function setup(): void
    {
        $this->container = new MockContainer($this);
    }

    /**
     * Test behavior when no notifications are waiting to be sent.
     *
     * @return void
     */
    public function testNoNotifications()
    {
        $searchTable = $this->container->createMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will($this->returnValue([]));
        $command = $this->getCommand(
            [
                'searchTable' => $searchTable,
                'scheduleOptions' => [],
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 0 searches\nDone processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but there is no
     * matching frequency configuration.
     *
     * @return void
     */
    public function testNotificationWithIllegalFrequency()
    {
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(
                    [
                        'search_object' => null,
                    ]
                ),
                'scheduleOptions' => [1 => 'Daily'],
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "ERROR: Search 1: unknown schedule: 7\nDone processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications have already been sent recently.
     *
     * @return void
     */
    public function testNotificationWithRecentExecution()
    {
        $lastDate = date('Y-m-d H:i:s');
        $overrides = [
            'last_notification_sent' => $lastDate,
            'search_object' => null,
        ];
        $lastDate = str_replace(' ', 'T', $lastDate) . 'Z';
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable($overrides),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "  Bypassing search 1: previous execution too recent (Weekly, $lastDate)\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but user does not
     * exist.
     *
     * @return void
     */
    public function testNotificationsWithMissingUser()
    {
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(
                    [
                        'search_object' => null,
                    ]
                ),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "WARNING: Search 1: user 2 does not exist \n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but an illegal backend
     * is involved.
     *
     * @return void
     */
    public function testNotificationsWithUnsupportedBackend()
    {
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getBackendId')
                ->will($this->returnValue('unsupported'));
            $results->expects($this->any())->method('getSearchId')
                ->will($this->returnValue(1));
        };
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(
                    [],
                    null,
                    null,
                    $resultsCallback
                ),
                'userTable' => $this->getMockUserTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "ERROR: Unsupported search backend unsupported for search 1\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but no search
     * results exist.
     *
     * @return void
     */
    public function testNotificationsWithNoSearchResults()
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')
                ->will($this->returnValue(true));
        };
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getSearchId')
                ->will($this->returnValue(1));
        };
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(
                    [],
                    $optionsCallback,
                    null,
                    $resultsCallback
                ),
                'userTable' => $this->getMockUserTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "  No results found for search 1\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent but no search
     * results exist.
     *
     * @return void
     */
    public function testNotificationsWithNoNewSearchResults()
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')
                ->will($this->returnValue(true));
        };
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getSearchId')
                ->will($this->returnValue(1));
            $results->expects($this->any())->method('getResults')
                ->will($this->returnValue($this->getMockSearchResultsSet()));
        };
        $command = $this->getCommand(
            [
                'searchTable' => $this->getMockSearchTable(
                    [],
                    $optionsCallback,
                    null,
                    $resultsCallback
                ),
                'userTable' => $this->getMockUserTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $zeroDate = str_replace(' ', 'T', date('Y-m-d H:i:s', 0)) . 'Z';
        $expected = "Processing 1 searches\n"
            . "  No new results for search (1): $zeroDate < 2000-01-01T00:00:00Z\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test behavior when notifications are waiting to be sent and new search
     * results exist.
     *
     * @return void
     */
    public function testNotificationsWithNewSearchResults()
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')
                ->will($this->returnValue(true));
        };
        $paramsCallback = function ($params) {
            $params->expects($this->any())->method('getCheckboxFacets')
                ->will($this->returnValue([]));
        };
        $date = date('Y-m-d H:i:s');
        $expectedDate = str_replace(' ', 'T', $date) . 'Z';
        $record = new \VuFindTest\RecordDriver\TestHarness();
        $record->setRawData(
            [
                'FirstIndexed' => $date,
            ]
        );
        $resultsCallback = function ($results) use ($record) {
            $results->expects($this->any())->method('getSearchId')
                ->will($this->returnValue(1));
            $results->expects($this->any())->method('getResults')
                ->will($this->returnValue($this->getMockSearchResultsSet($record)));
        };
        $message = 'sample message';
        $expectedViewParams = [
            'user' => $this->getMockUserObject(),
            'records' => [$record],
            'info' => [
                'baseUrl' => 'http://foo',
                'description' => null,
                'recordCount' => 1,
                'url' => 'http://foo',
                'unsubscribeUrl' => 'http://foo?id=1&key=',
                'checkboxFilters' => [],
                'filters' => null,
                'userInstitution' => 'My Institution',
            ],
        ];
        $renderer = $this->container->createMock(
            \Laminas\View\Renderer\PhpRenderer::class,
            ['render']
        );
        $renderer->expects($this->once())->method('render')
            ->with(
                $this->equalTo('Email/scheduled-alert.phtml'),
                $this->equalTo($expectedViewParams)
            )->will($this->returnValue($message));
        $mailer = $this->container->createMock(\VuFind\Mailer\Mailer::class);
        $mailer->expects($this->once())->method('send')
            ->with(
                $this->equalTo('fake@myuniversity.edu'),
                $this->equalTo('admin@myuniversity.edu'),
                $this->equalTo('My Site: translated text'),
                $this->equalTo($message)
            );
        $translator = $this->container->createMock(\Laminas\Mvc\I18n\Translator::class);
        $translator->expects($this->once())->method('translate')
            ->with($this->equalTo('Scheduled Alert Results'))
            ->will($this->returnValue('translated text'));
        $command = $this->getCommand(
            [
                'mailer' => $mailer,
                'renderer' => $renderer,
                'translator' => $translator,
                'searchTable' => $this->getMockSearchTable(
                    [],
                    $optionsCallback,
                    $paramsCallback,
                    $resultsCallback
                ),
                'userTable' => $this->getMockUserTable(),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Processing 1 searches\n"
            . "  New results for search (1): $expectedDate >= 2000-01-01T00:00:00Z\n"
            . "Done processing searches\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get mock search results.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record Record to return
     *
     * @return array
     */
    protected function getMockSearchResultsSet($record = null)
    {
        return [
            $record ?? $this->container->createMock(\VuFind\RecordDriver\SolrDefault::class),
        ];
    }

    /**
     * Create a list of fake notification objects.
     *
     * @param array    $overrides       Fields to override in the notification row.
     * @param callable $optionsCallback Callback to set expectations on options object
     * @param callable $paramsCallback  Callback to set expectations on params object
     * @param callable $resultsCallback Callback to set expectations on results object
     *
     * @return array
     */
    protected function getMockNotifications(
        $overrides = [],
        $optionsCallback = null,
        $paramsCallback = null,
        $resultsCallback = null
    ) {
        $defaults = [
            'id' => 1,
            'user_id' => 2,
            'session_id' => null,
            'created' => '2000-01-01 00:00:00',
            'title' => null,
            'saved' => 1,
            'checksum' => null,
            'notification_frequency' => 7,
            'last_notification_sent' => '2000-01-01 00:00:00',
            'notification_base_url' => 'http://foo',
        ];
        // Don't create the mock search (and thus set up assertions) unless
        // we actually need to. We use array_key_exists() instead of isset()
        // because the key may be explicitly set to a value of null.
        if (!array_key_exists('search_object', $overrides)) {
            $defaults['search_object'] = serialize(
                $this->getMockSearch(
                    $optionsCallback,
                    $paramsCallback,
                    $resultsCallback
                )
            );
        }
        $adapter = $this->container->createMock(\Laminas\Db\Adapter\Adapter::class);
        $row1 = $this->getMockBuilder(\VuFind\Db\Row\Search::class)
            ->setConstructorArgs([$adapter])
            ->onlyMethods(['save'])
            ->getMock();
        $row1->populate($overrides + $defaults, true);
        return [$row1];
    }

    /**
     * Get mock search results.
     *
     * @param callable $optionsCallback Callback to set expectations on options object
     * @param callable $paramsCallback  Callback to set expectations on params object
     * @param callable $resultsCallback Callback to set expectations on results object
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockSearchResults(
        $optionsCallback = null,
        $paramsCallback = null,
        $resultsCallback = null
    ) {
        $options = $this->container->createMock(\VuFind\Search\Solr\Options::class);
        if ($optionsCallback) {
            $optionsCallback($options);
        }
        $urlQuery = $this->container->createMock(\VuFind\Search\UrlQueryHelper::class);
        $params = $this->container->createMock(\VuFind\Search\Solr\Params::class);
        if ($paramsCallback) {
            $paramsCallback($params);
        }
        $results = $this->container->createMock(\VuFind\Search\Solr\Results::class);
        $results->expects($this->any())->method('getOptions')
            ->will($this->returnValue($options));
        $results->expects($this->any())->method('getUrlQuery')
            ->will($this->returnValue($urlQuery));
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        if ($resultsCallback) {
            $resultsCallback($results);
        }
        return $results;
    }

    /**
     * Get a minified search object
     *
     * @param callable $optionsCallback Callback to set expectations on options object
     * @param callable $paramsCallback  Callback to set expectations on params object
     * @param callable $resultsCallback Callback to set expectations on results object
     *
     * @return \VuFind\Search\Minified
     */
    protected function getMockSearch(
        $optionsCallback = null,
        $paramsCallback = null,
        $resultsCallback = null
    ) {
        $search = $this->container->createMock(\VuFind\Search\Minified::class);
        $search->expects($this->any())->method('deminify')
            ->with($this->equalTo($this->getMockResultsManager()))
            ->will(
                $this->returnValue(
                    $this->getMockSearchResults(
                        $optionsCallback,
                        $paramsCallback,
                        $resultsCallback
                    )
                )
            );
        return $search;
    }

    /**
     * Get a mock row representing a user.
     *
     * @return \VuFind\Db\Row\Search
     */
    protected function getMockUserObject()
    {
        $data = [
            'id' => 2,
            'username' => 'foo',
            'email' => 'fake@myuniversity.edu',
            'created' => '2000-01-01 00:00:00',
            'last_language' => 'en',
        ];
        $adapter = $this->container->createMock(\Laminas\Db\Adapter\Adapter::class);
        $user = new \VuFind\Db\Row\User($adapter);
        $user->populate($data, true);
        return $user;
    }

    /**
     * Get a notify command for testing.
     *
     * @param array $options Options to override
     *
     * @return NotifyCommand
     */
    protected function getCommand($options = [])
    {
        $renderer = $options['renderer']
            ?? $this->container->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $container->set('url', $this->container->createMock(\Laminas\View\Helper\Url::class));
        $renderer->setHelperPluginManager($container);
        $command = new NotifyCommand(
            $this->container->createMock(\VuFind\Crypt\HMAC::class),
            $renderer,
            $this->getMockResultsManager(),
            $options['scheduleOptions'] ?? [1 => 'Daily', 7 => 'Weekly'],
            new \Laminas\Config\Config(
                $options['configArray'] ?? [
                    'Site' => [
                        'institution' => 'My Institution',
                        'title' => 'My Site',
                        'email' => 'admin@myuniversity.edu',
                    ],
                ]
            ),
            $options['mailer'] ?? $this->container->createMock(\VuFind\Mailer\Mailer::class),
            $options['searchTable'] ?? $this->container->createMock(\VuFind\Db\Table\Search::class),
            $options['userTable'] ?? $this->container->createMock(\VuFind\Db\Table\User::class),
            $options['localeSettings'] ?? $this->container->createMock(\VuFind\I18n\Locale\LocaleSettings::class)
        );
        $command->setTranslator(
            $options['translator'] ?? $this->container->createMock(\Laminas\Mvc\I18n\Translator::class)
        );
        return $command;
    }

    /**
     * Create a mock results manager.
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    protected function getMockResultsManager()
    {
        // Use a static variable to ensure we only create a single shared instance
        // of the results manager.
        static $manager = false;
        if (!$manager) {
            $manager = $this->container
                ->createMock(\VuFind\Search\Results\PluginManager::class);
        }
        return $manager;
    }

    /**
     * Create a mock search table that returns a list of fake notification objects.
     *
     * @param array    $overrides       Fields to override in the notification row.
     * @param callable $optionsCallback Callback to set expectations on options object
     * @param callable $paramsCallback  Callback to set expectations on params object
     * @param callable $resultsCallback Callback to set expectations on results object
     *
     * @return array
     */
    protected function getMockSearchTable(
        $overrides = [],
        $optionsCallback = null,
        $paramsCallback = null,
        $resultsCallback = null
    ) {
        $searchTable = $this->container->createMock(\VuFind\Db\Table\Search::class);
        $searchTable->expects($this->once())->method('getScheduledSearches')
            ->will(
                $this->returnValue(
                    $this->getMockNotifications(
                        $overrides,
                        $optionsCallback,
                        $paramsCallback,
                        $resultsCallback
                    )
                )
            );
        return $searchTable;
    }

    /**
     * Create a mock user table that returns a fake user object.
     *
     * @return array
     */
    protected function getMockUserTable()
    {
        $user = $this->getMockUserObject();
        $userTable = $this->container->createMock(\VuFind\Db\Table\User::class);
        $userTable->expects($this->any())->method('getById')
            ->with($this->equalTo(2))->will($this->returnValue($user));
        return $userTable;
    }
}
