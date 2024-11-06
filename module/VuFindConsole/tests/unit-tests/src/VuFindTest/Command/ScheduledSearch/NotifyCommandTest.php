<?php

/**
 * ScheduledSearch/Notify command test.
 *
 * PHP version 8
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

use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\SearchServiceInterface;
use VuFindConsole\Command\ScheduledSearch\NotifyCommand;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\PathResolverTrait;

use function array_key_exists;

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
    use PathResolverTrait;

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
    public function testNoNotifications(): void
    {
        $searchService = $this->container->createMock(SearchServiceInterface::class);
        $searchService->expects($this->once())->method('getScheduledSearches')->willReturn([]);
        $command = $this->getCommand(
            [
                'searchService' => $searchService,
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
    public function testNotificationWithIllegalFrequency(): void
    {
        $command = $this->getCommand(
            [
                'searchService' => $this->getMockSearchService(
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
    public function testNotificationWithRecentExecution(): void
    {
        $lastDate = date('Y-m-d H:i:s');
        $overrides = [
            'last_notification_sent' => $lastDate,
            'search_object' => null,
        ];
        $lastDate = str_replace(' ', 'T', $lastDate) . 'Z';
        $command = $this->getCommand(
            [
                'searchService' => $this->getMockSearchService($overrides),
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
     * Test behavior when notifications are waiting to be sent but an illegal backend
     * is involved.
     *
     * @return void
     */
    public function testNotificationsWithUnsupportedBackend(): void
    {
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getBackendId')->willReturn('unsupported');
            $results->expects($this->any())->method('getSearchId')->willReturn(1);
        };
        $command = $this->getCommand(
            [
                'searchService' => $this->getMockSearchService(
                    [],
                    null,
                    null,
                    $resultsCallback
                ),
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
    public function testNotificationsWithNoSearchResults(): void
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')->willReturn(true);
        };
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getSearchId')->willReturn(1);
        };
        $command = $this->getCommand(
            [
                'searchService' => $this->getMockSearchService(
                    [],
                    $optionsCallback,
                    null,
                    $resultsCallback
                ),
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
    public function testNotificationsWithNoNewSearchResults(): void
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')->willReturn(true);
        };
        $resultsCallback = function ($results) {
            $results->expects($this->any())->method('getSearchId')->willReturn(1);
            $results->expects($this->any())->method('getResults')->willReturn($this->getMockSearchResultsSet());
        };
        $command = $this->getCommand(
            [
                'searchService' => $this->getMockSearchService(
                    [],
                    $optionsCallback,
                    null,
                    $resultsCallback
                ),
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
    public function testNotificationsWithNewSearchResults(): void
    {
        $optionsCallback = function ($options) {
            $options->expects($this->any())->method('supportsScheduledSearch')->willReturn(true);
        };
        $paramsCallback = function ($params) {
            $params->expects($this->any())->method('getCheckboxFacets')->willReturn([]);
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
            $results->expects($this->any())->method('getSearchId')->willReturn(1);
            $results->expects($this->any())->method('getResults')->willReturn($this->getMockSearchResultsSet($record));
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
            ->with('Email/scheduled-alert.phtml', $expectedViewParams)
            ->willReturn($message);
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
            ->willReturn('translated text');
        $command = $this->getCommand(
            [
                'mailer' => $mailer,
                'renderer' => $renderer,
                'translator' => $translator,
                'searchService' => $this->getMockSearchService(
                    [],
                    $optionsCallback,
                    $paramsCallback,
                    $resultsCallback
                ),
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
    protected function getMockSearchResultsSet(\VuFind\RecordDriver\AbstractBase $record = null): array
    {
        return [
            $record ?? $this->container->createMock(\VuFind\RecordDriver\SolrDefault::class),
        ];
    }

    /**
     * Create a list of fake notification objects.
     *
     * @param array     $overrides       Fields to override in the notification row.
     * @param ?callable $optionsCallback Callback to set expectations on options object
     * @param ?callable $paramsCallback  Callback to set expectations on params object
     * @param ?callable $resultsCallback Callback to set expectations on results object
     *
     * @return array
     */
    protected function getMockNotifications(
        array $overrides = [],
        ?callable $optionsCallback = null,
        ?callable $paramsCallback = null,
        ?callable $resultsCallback = null
    ): array {
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
            $defaults['search_object'] = $this->getMockSearch(
                $optionsCallback,
                $paramsCallback,
                $resultsCallback
            );
        }
        $data = $overrides + $defaults;
        $row1 = $this->createMock(SearchEntityInterface::class);
        $row1->method('getId')->willReturn($data['id']);
        $mockUser = $this->getMockUserObject($data['user_id']);
        $row1->method('getUser')->willReturn($mockUser);
        $row1->method('getSessionId')->willReturn($data['session_id']);
        $row1->method('getCreated')->willReturn(DateTime::createFromFormat('Y-m-d H:i:s', $data['created']));
        $row1->method('getTitle')->willReturn($data['title']);
        $row1->method('getSaved')->willReturn((bool)$data['saved']);
        $row1->method('getChecksum')->willReturn($data['checksum']);
        $row1->method('getNotificationFrequency')->willReturn($data['notification_frequency']);
        $row1->method('getLastNotificationSent')
            ->willReturn(DateTime::createFromFormat('Y-m-d H:i:s', $data['last_notification_sent']));
        $row1->method('getNotificationBaseUrl')->willReturn($data['notification_base_url']);
        $row1->method('getSearchObject')->willReturn($data['search_object'] ?? null);
        return [$row1];
    }

    /**
     * Get mock search results.
     *
     * @param ?callable $optionsCallback Callback to set expectations on options object
     * @param ?callable $paramsCallback  Callback to set expectations on params object
     * @param ?callable $resultsCallback Callback to set expectations on results object
     *
     * @return MockObject&\VuFind\Search\Solr\Results
     */
    protected function getMockSearchResults(
        ?callable $optionsCallback = null,
        ?callable $paramsCallback = null,
        ?callable $resultsCallback = null
    ): MockObject&\VuFind\Search\Solr\Results {
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
        $results->expects($this->any())->method('getOptions')->willReturn($options);
        $results->expects($this->any())->method('getUrlQuery')->willReturn($urlQuery);
        $results->expects($this->any())->method('getParams')->willReturn($params);
        if ($resultsCallback) {
            $resultsCallback($results);
        }
        return $results;
    }

    /**
     * Get a minified search object
     *
     * @param ?callable $optionsCallback Callback to set expectations on options object
     * @param ?callable $paramsCallback  Callback to set expectations on params object
     * @param ?callable $resultsCallback Callback to set expectations on results object
     *
     * @return MockObject&\VuFind\Search\Minified
     */
    protected function getMockSearch(
        ?callable $optionsCallback = null,
        ?callable $paramsCallback = null,
        ?callable $resultsCallback = null
    ): MockObject&\VuFind\Search\Minified {
        $search = $this->container->createMock(\VuFind\Search\Minified::class);
        $search->expects($this->any())->method('deminify')
            ->with($this->equalTo($this->getMockResultsManager()))
            ->willReturn(
                $this->getMockSearchResults(
                    $optionsCallback,
                    $paramsCallback,
                    $resultsCallback
                )
            );
        return $search;
    }

    /**
     * Get a mock row representing a user.
     *
     * @param string $userId User ID to be returned by mock.
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUserObject($userId = 2): MockObject&UserEntityInterface
    {
        $user = $this->createMock(UserEntityInterface::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getUsername')->willReturn('foo');
        $user->method('getEmail')->willReturn('fake@myuniversity.edu');
        $user->method('getCreated')->willReturn(\DateTime::createFromFormat('Y-m-d H:i:s', '2000-01-01 00:00:00'));
        $user->method('getLastLanguage')->willReturn('en');
        return $user;
    }

    /**
     * Get a notify command for testing.
     *
     * @param array $options Options to override
     *
     * @return NotifyCommand
     */
    protected function getCommand(array $options = []): NotifyCommand
    {
        $renderer = $options['renderer']
            ?? $this->container->createMock(\Laminas\View\Renderer\PhpRenderer::class);
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $container->set('url', $this->container->createMock(\Laminas\View\Helper\Url::class));
        $renderer->setHelperPluginManager($container);
        $command = new NotifyCommand(
            $this->container->createMock(\VuFind\Crypt\SecretCalculator::class),
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
            $options['searchService'] ?? $this->container->createMock(SearchServiceInterface::class),
            $options['localeSettings'] ?? $this->container->createMock(\VuFind\I18n\Locale\LocaleSettings::class)
        );
        $command->setTranslator(
            $options['translator'] ?? $this->container->createMock(\Laminas\Mvc\I18n\Translator::class)
        );
        $command->setPathResolver($this->getPathResolver());
        return $command;
    }

    /**
     * Create a mock results manager.
     *
     * @return MockObject&\VuFind\Search\Results\PluginManager
     */
    protected function getMockResultsManager(): MockObject&\VuFind\Search\Results\PluginManager
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
     * @param array     $overrides       Fields to override in the notification row.
     * @param ?callable $optionsCallback Callback to set expectations on options object
     * @param ?callable $paramsCallback  Callback to set expectations on params object
     * @param ?callable $resultsCallback Callback to set expectations on results object
     *
     * @return MockObject&SearchServiceInterface
     */
    protected function getMockSearchService(
        array $overrides = [],
        ?callable $optionsCallback = null,
        ?callable $paramsCallback = null,
        ?callable $resultsCallback = null
    ): MockObject&SearchServiceInterface {
        $searchService = $this->container->createMock(SearchServiceInterface::class);
        $searchService->expects($this->once())->method('getScheduledSearches')
            ->willReturn(
                $this->getMockNotifications(
                    $overrides,
                    $optionsCallback,
                    $paramsCallback,
                    $resultsCallback
                )
            );
        return $searchService;
    }
}
