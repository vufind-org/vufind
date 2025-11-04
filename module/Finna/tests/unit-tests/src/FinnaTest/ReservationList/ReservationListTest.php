<?php

/**
 * ReservationList test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\ReservationList;

use DateTime;
use Exception;
use Finna\Auth\ILSAuthenticator;
use Finna\Cache\Manager;
use Finna\Db\Row\FinnaResourceList;
use Finna\Db\Service\FinnaResourceListResourceService;
use Finna\Db\Service\FinnaResourceListService;
use Finna\Db\Service\UserService;
use Finna\Record\Loader as RecordLoader;
use Finna\ReservationList\Handler\Disec;
use Finna\ReservationList\Handler\Email;
use Finna\ReservationList\Handler\PluginManager as HandlerPluginManager;
use Finna\ReservationList\ReservationListService;
use Generator;
use Laminas\Cache\Storage\Adapter\FilesystemOptions;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Session\Container;
use Laminas\View\Renderer\PhpRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Yaml\Yaml;
use VuFind\Db\Row\User;
use VuFind\Db\Service\PluginManager;
use VuFind\Db\Service\ResourceService;
use VuFind\Db\Service\UserCardService;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Record\Cache;
use VuFind\Record\ResourcePopulator;
use VuFindHttp\HttpService;
use VuFindTest\Container\MockContainer;

/**
 * Reservation list tests
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ReservationListTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\TranslatorTrait;
    use \FinnaTest\Traits\MockLoadersTrait;
    use \FinnaTest\Traits\MockServicesTrait;

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
     * Get mocked reservation list service
     *
     * @param ?MockObject $mockHttpService       Http service
     * @param ?MockObject $listPluginManager     List plugin manager
     * @param array       $reservationListConfig Reservation list config
     *
     * @return MockObject
     */
    protected function getReservationListService(
        ?MockObject $mockHttpService = null,
        ?MockObject $listPluginManager = null,
        array $reservationListConfig = [],
    ): MockObject {
        $adapterOptions = new FilesystemOptions();
        $storage = $this->getMockBuilder(StorageInterface::class)->disableOriginalConstructor()->getMock();
        $storage->expects($this->any())->method('getOptions')->willReturn($adapterOptions);
        $cacheManager = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()->getMock();
        $cacheManager->expects($this->any())->method('getCache')->willReturn($storage);
        $service = $this->getMockBuilder(ReservationListService::class)->onlyMethods(['createListForUser'])
        ->setConstructorArgs([
          $this->container->createMock(FinnaResourceListService::class),
          $this->container->createMock(FinnaResourceListResourceService::class),
          $this->container->createMock(ResourceService::class),
          $this->container->createMock(UserService::class),
          $this->container->createMock(ResourcePopulator::class),
          $this->container->createMock(RecordLoader::class),
          $this->container->createMock(Cache::class),
          $this->container->createMock(Container::class),
          $mockHttpService ??= $this->container->createMock(HttpService::class),
          $this->container->createMock(ILSAuthenticator::class),
          $cacheManager,
          $listPluginManager ??= $this->container->createMock(HandlerPluginManager::class),
          $reservationListConfig,
        ])->getMock();
        $newListTemplate = $this->getMockBuilder(FinnaResourceList::class)->onlyMethods(['getUser'])
          ->disableOriginalConstructor()->getMock();
        $service->expects($this->any())->method('createListForUser')->willReturnCallback(
            function ($user, $params) use ($newListTemplate, $service) {
                $cloned = clone $newListTemplate;
                if ($params) {
                    $cloned = $service->populateListValues($cloned, $user, $params);
                }
                $cloned->expects($this->any())->method('getUser')->willReturn($user);
                return $cloned;
            }
        );
        return $service;
    }

    /**
     * Get a mock user.
     *
     * @param int $userID Id for the user created
     *
     * @return User
     */
    protected function getMockUser(int $userID = 0)
    {
        $user = $this->container->createMock(User::class, [
          'getId',
          'getFirstname',
          'getLastname',
          'getEmail',
          'getCatId',
        ]);
        $user->method('getId')->willReturn($userID);
        $user->method('getFirstname')->willReturn('Testaaja');
        $user->method('getLastname')->willReturn('von Testaaja');
        $user->method('getEmail')->willReturn('testaaja@testeri.fi');
        $user->method('getCatId')->willReturn('');
        return $user;
    }

    /**
     * Data provider for properly set list data
     *
     * @return array
     */
    public static function getProperListData(): array
    {
        return [
            'list with only user' => [
                1,
                [],
            ],
            'list with user and prefill' => [
                2,
                [
                    'title' => 'test_title',
                    'desc' => 'Test Description',
                    'institution' => 'Test Institution',
                    'listIdentifier' => 'test_list_identifier',
                    'connection' => 'email',
                ],
            ],
        ];
    }

    /**
     * Data provider for insufficient list data
     *
     * @return array
     */
    public static function getFailingListData(): array
    {
        return [
            'list with unknown key value pair' => [
                1,
                [
                'test' => 'test',
                ],
            ],
            'list with user and title missing' => [
                2,
                [
                'desc' => 'Test Description',
                'institution' => 'Test Institution',
                'listIdentifier' => 'test_list_identifier',
                'connection' => 'email',
                ],
            ],
        ];
    }

    /**
     * Test list creation
     *
     * @param int   $id      User id
     * @param array $prefill Data to prefill the list with
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getProperListData')]
    public function testListCreation(int $id, array $prefill): void
    {
        $user = $this->getMockUser($id);

        $service = $this->getReservationListService();
        $newList = $service->createListForUser($user, $prefill);
        $this->assertEquals($id, $newList->getUser()->getId());
    }

    /**
     * Test failing list creation
     *
     * @param int   $id      User id
     * @param array $prefill Array to prefill the list with
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getFailingListData')]
    public function testFailingListCreation(int $id, array $prefill): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing values to populate list');
        $user = $this->getMockUser($id);
        $service = $this->getReservationListService();
        @$service->createListForUser($user, $prefill);
    }

    /**
     * Data provider for testing user access for lists
     *
     * @return array
     */
    public static function getUserAccessSuccessData(): array
    {
        return [
            'first owner pair' => [
                1,
                1,
            ],
            'second owner pair' => [
                2,
                2,
            ],
        ];
    }

    /**
     * Data provider for testing user access for lists
     *
     * @return array
     */
    public static function getUserAccessFailureData(): array
    {
        return [
            'no current user' => [
                1,
                null,
            ],
            'different user' => [
                2,
                4,
            ],
        ];
    }

    /**
     * Test user list access
     *
     * @param int $ownerId   Owner id for the list
     * @param int $currentId Current user id for the list
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getUserAccessSuccessData')]
    public function testUserAccessSuccess(int $ownerId, int $currentId): void
    {
        $ownerUser = $this->getMockUser($ownerId);

        $service = $this->getReservationListService();
        $newList = $service->createListForUser($ownerUser);
        $currentUser = $this->getMockUser($currentId);
        $this->assertEquals(true, $service->userCanEditList($currentUser, $newList));
    }

    /**
     * Test user list access failure
     *
     * @param int  $ownerId   Owner id for the list
     * @param ?int $currentId Current user id for the list or null for no user
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getUserAccessFailureData')]
    public function testUserAccessFailure(int $ownerId, ?int $currentId = null): void
    {
        $ownerUser = $this->getMockUser($ownerId);
        $service = $this->getReservationListService();
        $newList = $service->createListForUser($ownerUser);
        $currentUser = $currentId === null ? null : $this->getMockUser($currentId);
        $this->assertEquals(false, $service->userCanEditList($currentUser, $newList));
    }

    /**
     * Data provider for testing user deletion of lists
     *
     * @return array
     */
    public static function getTestDeletingListData(): array
    {
        return [
          'no current user' => [
            1,
            null,
            'list_access_denied',
          ],
          'different user' => [
            2,
            4,
            'list_access_denied',
          ],
          'same user' => [
            1,
            1,
            'List deleted',
          ],
        ];
    }

    /**
     * Test deleting list
     *
     * @param int    $ownerId   Owner id for the list
     * @param ?int   $currentId Current user id for the list or null for no user
     * @param string $expected  Expected error value. Success for deletion also uses exception
     *                          for asserting that everything went well.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDeletingListData')]
    public function testDeletingList(int $ownerId, ?int $currentId, string $expected): void
    {
        if ($expected === 'list_access_denied') {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage($expected);
        }
        $ownerUser = $this->getMockUser($ownerId);

        $service = $this->getReservationListService();
        $newList = $service->createListForUser($ownerUser);
        $currentUser = $currentId === null ? null : $this->getMockUser($currentId);
        $service->destroyList($newList, $currentUser);
        $this->assertEquals(true, $service->userCanEditList($currentUser, $newList));
    }

    /**
     * Data provider for testing setting a list being ordered
     *
     * @return array
     */
    public static function getTestSettingListOrderedData(): array
    {
        return [
            'all keys and values present' => [
            1,
            [
                'pickup_date' => '2019-01-01',
                'connection' => 'email',
                'external_id' => '123calico123',
            ],
            [
                'pickup_date' => DateTime::createFromFormat('Y-m-d', '2019-01-01'),
                'connection' => 'email',
                'external_id' => '123calico123',
            ],
            ],
            'missing connection' => [
            1,
            [
                'pickup_date' => '2019-01-01',
                'external_id' => '123calico123',
            ],
            [
                'pickup_date' => DateTime::createFromFormat('Y-m-d', '2019-01-01'),
                'connection' => ReservationListService::DEFAULT_CONNECTION_HANDLER,
                'external_id' => '123calico123',
            ],
            ],
            'missing external id' => [
            1,
            [
                'pickup_date' => '2019-01-01',
                'connection' => 'disec',
            ],
            [
                'pickup_date' => DateTime::createFromFormat('Y-m-d', '2019-01-01'),
                'connection' => 'disec',
                'external_id' => null,
            ],
            ],
            'missing pickup_date' => [
            1,
            [
                'connection' => 'email',
                'external_id' => '123calico123',
            ],
            [
                'pickup_date' => null,
                'connection' => 'email',
                'external_id' => '123calico123',
            ],
            ],
        ];
    }

    /**
     * Test deleting list
     *
     * @param int   $ownerId  Owner id for the list
     * @param array $data     Data to pass for the list being ordered
     * @param array $expected Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestSettingListOrderedData')]
    public function testSettingListOrdered(int $ownerId, array $data, array $expected): void
    {
        if (!isset($data['pickup_date'])) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Missing pickup date');
        }
        $ownerUser = $this->getMockUser($ownerId);
        $service = $this->getReservationListService();

        $newList = $service->createListForUser($ownerUser);
        $service->setListOrdered($ownerUser, $newList, $data);
        $this->assertEquals(true, $newList->__get('connection'));
    }

    /**
     * Get test data for testing handlers
     *
     * @return array
     */
    public static function getTestHandlerData(): array
    {
        return [
          'default order' => [
            'Example Institution',
            'list_with_email',
            [
              'full_name' => 'Test Tester',
              'email' => 'testaaja@testeri.fi',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
              'listId' => null,
              'institution' => 'Example Institution',
              'listIdentifier' => 'list_with_email',
              'full_name' => 'Test Tester',
              'email' => 'testaaja@testeri.fi',
              'record_ids_text' => '',
              'record_source_and_ids' => [],
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
              'phone' => null,
              'card_info' => 'Patron card name',
            ],
          ],
          'different name given' => [
            'Example Institution',
            'list_with_email',
            [
              'full_name' => 'Pouta Pakkanen',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
              'listId' => null,
              'institution' => 'Example Institution',
              'listIdentifier' => 'list_with_email',
              'full_name' => 'Pouta Pakkanen',
              'email' => 'patronemail@email.fi',
              'record_ids_text' => '',
              'record_source_and_ids' => [],
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
              'phone' => null,
              'card_info' => 'Patron card name',
            ],
          ],
          'disec get list values' => [
            'Example Institution',
            'list_with_disec',
            [
              'full_name' => 'Pouta Pekkanen',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
              'listId' => null,
              'institution' => 'Example Institution',
              'listIdentifier' => 'list_with_disec',
              'full_name' => 'Pouta Pekkanen',
              'email' => 'patronemail@email.fi',
              'record_ids_text' => '',
              'record_source_and_ids' => [],
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
              'phone' => null,
              'card_info' => 'Patron card name',
            ],
          ],
        ];
    }

    /**
     * Creates an instance of a plugin manager for getting connection handlers
     *
     * @param ?MockObject $mockDisec              Disec handler
     * @param ?MockObject $mockEmail              Email handler
     * @param ?MockObject $yamlReader             Yaml reader
     * @param ?MockObject $mockForm               Form
     * @param ?MockObject $viewRenderer           View renderer
     * @param ?MockObject $reservationListService Reservation list service
     * @param ?MockObject $httpService            Http service
     * @param ?MockObject $ilsAuthenticator       ILS Authenticator
     * @param ?MockObject $userCardService        User card service
     * @param array       $listConfig             Reservation list config
     * @param array       $handlerServices        Handler services mapping for plugin manager
     *
     * @return MockObject
     */
    public function getPluginManager(
        ?MockObject $mockDisec = null,
        ?MockObject $mockEmail = null,
        ?MockObject $yamlReader = null,
        ?MockObject $mockForm = null,
        ?MockObject $viewRenderer = null,
        ?MockObject $reservationListService = null,
        ?MockObject $httpService = null,
        ?MockObject $ilsAuthenticator = null,
        ?MockObject $userCardService = null,
        array $listConfig = [],
        array $handlerServices = [],
    ): MockObject {
        if (!$listConfig) {
            $listConfig = Yaml::parse($this->getFixture('reservationlist/ReservationList.yaml', 'Finna'));
        }
        if (null === $yamlReader) {
            $yamlReader = $this->getMockBuilder(\Finna\Config\YamlReader::class)
              ->onlyMethods(['getFinna'])->disableOriginalConstructor()->getMock();
            $yamlReader->expects($this->any())->method('getFinna')
              ->willReturnMap([['ReservationList.yaml', 'config/finna', true, $listConfig]]);
        }
        if (null === $viewRenderer) {
            $viewRenderer = $this->getMockBuilder(PhpRenderer::class)->disableOriginalConstructor()->getMock();
            $viewRenderer->expects($this->any())->method('render')->willReturn('');
        }

        if (null === $ilsAuthenticator) {
            $ilsAuthenticator = $this->getMockBuilder(ILSAuthenticator::class)->disableOriginalConstructor()->getMock();
            $ilsAuthenticator->expects($this->any())->method('storedCatalogLogin')->willReturn([
              'firstname' => 'Testaaja',
              'lastname' => 'von Testaaja',
              'patron_id' => 'test.testid',
              '__local_id' => 'testid',
              '__local_cat_username' => 'test_cat_username',
            ]);
        }

        if (null === $userCardService) {
            $userCardService = $this->getMockBuilder(UserCardService::class)->disableOriginalConstructor()->getMock();
            $userCardService->expects($this->any())->method('getLibraryCards')->willReturn([]);
        }

        $dbPluginManager = $this->getMockBuilder(PluginManager::class)->disableOriginalConstructor()->getMock();
        $dbPluginManager->expects($this->any())->method('get')->willReturnMap([
          [UserCardServiceInterface::class, null, $userCardService],
        ]);

        $reservationListService ??= $this->getReservationListService();
        $httpService ??= $this->getHttpService([]);

        if (null === $mockForm) {
            $mockForm = $this->getMockBuilder(\Finna\ReservationList\Form\Form::class)
              ->disableOriginalConstructor()->getMock();
            $mockForm->expects($this->any())->method('mapRequestParamsToFieldValues')->willReturn([]);
        }

        if (!$handlerServices) {
            $handlerServices = [
              [\Finna\Config\YamlReader::class, $yamlReader],
              [\VuFindHttp\HttpService::class, $httpService],
              [\VuFind\Record\Loader::class, $this->getFinnaRecordLoader()],
              [\Finna\ReservationList\Form\Form::class, $mockForm],
              [ILSAuthenticator::class, $ilsAuthenticator],
              [PluginManager::class, $dbPluginManager],
              ['ViewRenderer', $viewRenderer],
              [
                \Finna\ReservationList\ReservationListService::class,
                $this->getReservationListService(reservationListConfig: $listConfig),
              ],
            ];
        }

        if ($mockDisec === null) {
            $mockDisec = $this->getMockBuilder(Disec::class)
              ->onlyMethods(['getService', 'debug', 'getPreferredCardInfo'])
              ->disableOriginalConstructor()->getMock();
            $mockDisec->expects($this->any())->method('getService')->willReturnMap($handlerServices);
            $mockDisec->expects($this->any())->method('getPreferredCardInfo')->willReturn([
              'patron_id' => '11',
              'full_name' => 'Test Tester',
              'email' => 'patronemail@email.fi',
              'card_name' => 'Patron card name',
            ]);
        }

        if ($mockEmail === null) {
            $mockEmail = $this->getMockBuilder(Email::class)
              ->onlyMethods(['getService', 'debug', 'sendEmail', 'getPreferredCardInfo'])
              ->disableOriginalConstructor()->getMock();
            $mockEmail->expects($this->any())->method('getService')->willReturnMap($handlerServices);
            $mockEmail->expects($this->any())->method('sendEmail')->willReturn(true);
            $mockEmail->expects($this->any())->method('getPreferredCardInfo')->willReturn([
              'patron_id' => '11',
              'full_name' => 'Test Tester',
              'email' => 'patronemail@email.fi',
              'card_name' => 'Patron card name',
            ]);
        }

        $listPluginMap = [
          ['disec', null, $mockDisec],
          ['email', null, $mockEmail],
        ];

        $mockListPluginManager = $this->getMockBuilder(HandlerPluginManager::class)->onlyMethods(['get'])
          ->disableOriginalConstructor()->getMock();
        $mockListPluginManager->expects($this->any())->method('get')->willReturnMap($listPluginMap);

        return $mockListPluginManager;
    }

    /**
     * Test list value returns for handlers
     *
     * @param string $institution    Institution for list
     * @param string $listIdentifier List identifier for list
     * @param array  $requestValues  Request values to test
     * @param array  $expected       Expected values to be returned
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHandlerData')]
    public function testHandlers(
        string $institution,
        string $listIdentifier,
        array $requestValues,
        array $expected
    ): void {
        $ownerUser = $this->getMockUser(1);
        $reservationListConfig = Yaml::parse($this->getFixture('reservationlist/ReservationList.yaml', 'Finna'));
        $listPluginManager = $this->getPluginManager();
        $service = $this->getReservationListService(
            listPluginManager: $listPluginManager,
            reservationListConfig: $reservationListConfig
        );
        $handler = $service->getListHandler($institution, $listIdentifier);

        $list = $service->createListForUser($ownerUser, [
          'title' => 'Test List Title',
          'desc' => 'Test List Desc',
          'institution' => $handler->getInstitution(),
          'listIdentifier' => $handler->getIdentifier(),
          'connection' => $handler->getConnectionType(),
        ]);

        $returned = $handler->getValuesForListOrder($list, $ownerUser, $requestValues);
        $this->assertEquals($expected, $returned);
    }

    /**
     * Test disec data sending
     *
     * @return void
     */
    public function testDisecPlaceOrder(): void
    {
        $user = $this->getMockUser(1);
        $reservationListConfig = Yaml::parse($this->getFixture('reservationlist/ReservationList.yaml', 'Finna'));
        $urlAndClientMap = [
          'http://disectest.url.fi/orders' => [
            'success' => true,
            'body' => '{"id": 123123}',
          ],
        ];
        $httpService = $this->getHttpService($urlAndClientMap);
        $listPluginManager = $this->getPluginManager(
            httpService: $httpService
        );
        $service = $this->getReservationListService(
            listPluginManager: $listPluginManager,
            reservationListConfig: $reservationListConfig,
            mockHttpService: $httpService
        );
        $handler = $service->getListHandler('Example Institution', 'list_with_disec');
        $testValues = [
          'listId' => null,
          'institution' => 'Example Institution',
          'listIdentifier' => 'list_with_disec',
          'firstName' => 'Pouta',
          'lastName' => 'Pakkanen',
          'email' => 'testaaja@testeri.fi',
          'record_ids_text' => '',
          'record_source_and_ids' => [],
          'pickup_date' => '2025-01-01',
          'message' => 'Test message',
        ];
        $result = $handler->placeOrder($testValues, $user);
        $this->assertEquals([
          'external_id' => '123123',
          'connection' => 'disec',
          'pickup_date' => '2025-01-01',
          'success' => true,
        ], $result);
    }

    /**
     * Test email data sending
     *
     * @return void
     */
    public function testEmailPlaceOrder(): void
    {
        $user = $this->getMockUser(1);
        $reservationListConfig = Yaml::parse($this->getFixture('reservationlist/ReservationList.yaml', 'Finna'));
        $urlAndClientMap = [
          'http://disectest.url.fi/orders' => [
            'success' => true,
            'body' => '{"id": 123123}',
          ],
        ];
        $httpService = $this->getHttpService($urlAndClientMap);
        $listPluginManager = $this->getPluginManager(
            httpService: $httpService
        );
        $service = $this->getReservationListService(
            listPluginManager: $listPluginManager,
            reservationListConfig: $reservationListConfig,
            mockHttpService: $httpService
        );
        $handler = $service->getListHandler('Example Institution', 'list_with_email');
        $testValues = [
          'listId' => null,
          'institution' => 'Example Institution',
          'listIdentifier' => 'list_with_email',
          'full_name' => 'Pouta Pakkanen',
          'email' => 'testaaja@testeri.fi',
          'record_ids_text' => '',
          'record_source_and_ids' => [],
          'pickup_date' => '2025-01-01',
          'message' => 'Test message',
        ];
        $result = $handler->placeOrder($testValues, $user);
        $this->assertEquals([
          'external_id' => null,
          'connection' => 'email',
          'pickup_date' => '2025-01-01',
          'success' => true,
        ], $result);
    }

    /**
     * Data provider for testgetListHandlerFromApi
     *
     * @return Generator
     */
    public static function getTestGetListHandlerFromApiData(): Generator
    {
        $fixturePath = 'reservationlist/ReservationList_api.yaml';
        yield 'test working url' => [
            true,
            $fixturePath,
            [
              'type' => 'email',
              'Sender' => [
                'name' => 'sender_test',
                'email' => 'sender_email@email.fi',
              ],
              'Subject' => 'Reservation List',
            ],
        ];
        yield 'test nonworking url' => [
          false,
          $fixturePath,
          [],
        ];
    }

    /**
     * Test list fetch from an api endpoint
     *
     * @param bool   $success     Is the request successful
     * @param string $fixturePath Fixture path
     * @param array  $expected    Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestgetListHandlerFromApiData')]
    public function testGetListHandlerFromApi(bool $success, string $fixturePath, array $expected): void
    {
        $config = Yaml::parse($this->getFixture($fixturePath, 'Finna'));
        $configJSON = json_encode($config);
        $urlAndClientMap = [
            $config['Settings']['url'] => [
              'success' => $success,
              'body' => '{"data":' . $configJSON . '}',
            ],
        ];
        $httpService = $this->getHttpService($urlAndClientMap);
        $listPluginManager = $this->getPluginManager(
            httpService: $httpService
        );
        $service = $this->getReservationListService(
            listPluginManager: $listPluginManager,
            reservationListConfig: $config,
            mockHttpService: $httpService
        );
        $listHandler = $service->getListHandler('Example Institution', 'list_with_email');
        $this->assertEquals($expected, $listHandler->getConnectionSettings());
        $this->assertEquals($success, $listHandler->isEnabled());
    }
}
