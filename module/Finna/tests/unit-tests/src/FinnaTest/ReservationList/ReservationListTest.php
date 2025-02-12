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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
use Finna\Db\Row\FinnaResourceList;
use Finna\Db\Service\FinnaResourceListResourceService;
use Finna\Db\Service\FinnaResourceListService;
use Finna\ReservationList\Handler\Disec;
use Finna\ReservationList\Handler\Email;
use Finna\ReservationList\Handler\HandlerFactory;
use Finna\ReservationList\Handler\PluginManager as HandlerPluginManager;
use Finna\ReservationList\ReservationListService;
use Finna\ReservationList\ReservationListServiceFactory;
use Generator;
use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGatewayFactory;
use VuFind\Db\Row\User;
use VuFind\Db\Service\PluginManager;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Log\Logger;
use VuFind\Record\Loader;
use VuFind\Record\ResourcePopulator;
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
     * Set classes to container required for testing
     *
     * @return void
     */
    public function setContainer(): void
    {
        $this->container->set(
            'Config',
            [
            'vufind' =>
            [
              'plugin_managers' => [
                'reservationlist_handler' => [],
              ],
            ],
            ],
        );
        $mockResourceListResourceService = $this->container->createMock(
            FinnaResourceListResourceService::class,
            ['unlinkResources']
        );
        $mockResourceService = $this->container->createMock(\VuFind\Db\Service\ResourceService::class);

        $this->container->set(\VuFind\Db\Service\PluginManager::class, $this->container);

        $this->container->set(ResourceServiceInterface::class, $mockResourceService);
        $this->container->set(
            \Finna\Db\Service\FinnaResourceListResourceServiceInterface::class,
            $mockResourceListResourceService
        );

        // Create three mocked records for testing sending disec data.
        $mockRecord = $this->container->createMock(\Finna\RecordDriver\SolrEad3::class, ['getIdentifier']);
        $loadedBatch = [];
        for ($i = 0; $i < 3; $i++) {
            $cloneRecord = clone $mockRecord;
            $cloneRecord->expects($this->any())->method('getIdentifier')->willReturn(["identifier_record_$i"]);
            $loadedBatch[] = $cloneRecord;
        }
        $mockRecordLoader = $this->container->createMock(Loader::class, ['loadBatch']);
        $mockRecordLoader->expects($this->any())->method('loadBatch')->willReturn($loadedBatch);
        $this->container->set(Loader::class, $mockRecordLoader);

        $mockMailer = $this->container->createMock(\VuFind\Mailer\Mailer::class);
        $this->container->set(\VuFind\Mailer\Mailer::class, $mockMailer);
        $mockResourcePopulator = $this->container->createMock(ResourcePopulator::class);
        $this->container->set(ResourcePopulator::class, $mockResourcePopulator);

        $mockLogger = $this->container->createMock(Logger::class, ['__destruct']);
        $mockLogger->setWriters(new \Laminas\Stdlib\SplPriorityQueue());
        $this->container->set(Logger::class, $mockLogger);

        $resourceList = $this->getResourceList();
        $mockResourceListService = $this->container->createMock(
            FinnaResourceListService::class,
            ['createEntity', 'deleteResourceList', 'persistEntity']
        );
        $mockResourceListService->expects($this->any())->method('createEntity')->willReturn($resourceList);
        $mockResourceListService->expects($this->any())->method('deleteResourceList')
        ->will($this->throwException(new \Exception('List deleted')));
        $this->container->set(\Finna\Db\Service\FinnaResourceListServiceInterface::class, $mockResourceListService);
    }

    /**
     * Data provider for getting list properties
     *
     * @return Generator
     */
    public static function getListPropertyData(): Generator
    {
        yield 'list with email connection' => [
          ['Example Institution', 'list_with_email'],
          'reservationlist/ReservationList.yaml',
          [
            'properties' => [
              'Enabled' => true,
              'Recipient' => [
                [
                  'name' => 'name_of_the_recipient_1',
                  'email' => 'email_of_the_recipient_1',
                ],
                [
                  'name' => 'name_of_the_recipient_2',
                  'email' => 'email_of_the_recipient_2',
                ],
              ],
              'Datasources' => [
                'datasource_1',
                'datasource_2',
              ],
              'Information' => [
                'Address' => 'teststreet 10',
                'Postal' => '000001',
                'City' => 'Test city',
              ],
              'LibraryCardSources' => [
                'connection_established_to_use_lists',
              ],
              'Forms' => [
                  'PlaceOrder' => 'default',
              ],
              'Connection' =>  [
                  'type' => 'email',
                  'Sender' => [
                    'name' => 'sender_test',
                    'email' => 'sender_email@email.fi',
                  ],
                  'Subject' => 'Reservation List',
              ],
              'Identifier' => 'list_with_email',
            ],
            'institution_information' => [
              'name' => 'Example Institution Name',
              'address' => 'Example Institution address',
              'postal' => 'Example Institution postal',
              'city' => 'Example Institution city',
              'email' => 'Example Institution email',
            ],
            'translation_keys' => [
              'title' => 'ReservationList::list_title_Example Institution_list_with_email',
              'description' => 'ReservationList::list_description_Example Institution_list_with_email',
            ],
          ],
        ];

        yield 'list with disec connection' => [
          ['Example Institution', 'list_with_disec'],
          'reservationlist/ReservationList.yaml',
          [
            'properties' => [
              'Enabled' => true,
              'Recipient' => [],
              'Datasources' => [
                'datasource_1',
                'datasource_2',
              ],
              'Information' => [
                'Address' => 'teststreet 10',
                'Postal' => '000001',
                'City' => 'Test city',
              ],
              'LibraryCardSources' => [
                'connection_established_to_use_lists',
              ],
              'Forms' => [
                  'PlaceOrder' => 'default',
              ],
              'Connection' =>  [
                'type' => 'disec',
                'base_url' => 'http://disectest.url.fi/',
                'secret' => 'verysecretphrase',
              ],
              'Identifier' => 'list_with_disec',
            ],
            'institution_information' => [
              'name' => 'Example Institution Name',
              'address' => 'Example Institution address',
              'postal' => 'Example Institution postal',
              'city' => 'Example Institution city',
              'email' => 'Example Institution email',
            ],
            'translation_keys' => [
              'title' => 'ReservationList::list_title_Example Institution_list_with_disec',
              'description' => 'ReservationList::list_description_Example Institution_list_with_disec',
            ],
          ],
        ];

        yield 'list which is not enabled' => [
        ['Example Institution', 'list_not_enabled'],
        'reservationlist/ReservationList.yaml',
        [
          'properties' => [
            'Enabled' => false,
            'Recipient' => [],
            'Datasources' => [
              'datasource_1',
              'datasource_2',
            ],
            'Information' => [
              'Address' => 'teststreet 10',
              'Postal' => '000001',
              'City' => 'Test city',
            ],
            'LibraryCardSources' => [
              'connection_established_to_use_lists',
            ],
            'Forms' => [
                'PlaceOrder' => 'default',
            ],
            'Connection' =>  [
                'type' => 'disec',
                'base_url' => 'http://disectest.url.fi/',
                'secret' => 'verysecretphrase',
            ],
            'Identifier' => 'list_not_enabled',
          ],
          'institution_information' => [
            'name' => 'Example Institution Name',
            'address' => 'Example Institution address',
            'postal' => 'Example Institution postal',
            'city' => 'Example Institution city',
            'email' => 'Example Institution email',
          ],
          'translation_keys' => [
            'title' => 'ReservationList::list_title_Example Institution_list_not_enabled',
            'description' => 'ReservationList::list_description_Example Institution_list_not_enabled',
          ],
        ],
        ];

        yield 'list with insufficient settings' => [
            ['Example Institution', 'list_insufficient_data'],
            'reservationlist/ReservationList.yaml',
            [
                'properties' => [
                    'Enabled' => false,
                    'Recipient' => [
                      [
                        'name' => 'name_of_the_recipient_1',
                        'email' => 'email_of_the_recipient_1',
                      ],
                      [
                        'name' => 'name_of_the_recipient_2',
                        'email' => 'email_of_the_recipient_2',
                      ],
                    ],
                    'Datasources' => [
                    'datasource_1',
                    'datasource_2',
                    ],
                    'Information' => [],
                    'LibraryCardSources' => [
                    'connection_established_to_use_lists',
                    ],
                    'Forms' => [
                        'PlaceOrder' => 'default',
                    ],
                    'Connection' =>  [
                        'type' => 'email',
                        'Sender' => [
                        'name' => 'Service sender',
                        'email' => 'test@noreply.fi',
                        ],
                        'Subject' => 'Reservation List',
                    ],
                    'Identifier' => 'list_insufficient_data',
                ],
                'institution_information' => [
                    'name' => 'Example Institution Name',
                    'address' => 'Example Institution address',
                    'postal' => 'Example Institution postal',
                    'city' => 'Example Institution city',
                    'email' => 'Example Institution email',
                ],
                'translation_keys' => [
                    'title' => 'ReservationList::list_title_Example Institution_list_insufficient_data',
                    'description' => 'ReservationList::list_description_Example Institution_list_insufficient_data',
                ],
            ],
        ];
        yield 'no lists defined' => [
            ['Example Institution', 'no_lists_defined'],
            'reservationlist/ReservationList_empty.yaml',
            [
                'properties' => [
                    'Enabled' => false,
                    'Recipient' => [],
                    'Datasources' => [],
                    'Information' => [],
                    'LibraryCardSources' => [],
                    'Forms' => [
                        'PlaceOrder' => 'default',
                    ],
                    'Connection' =>  [
                        'type' => 'email',
                    ],
                    'Identifier' => false,
                ],
                'institution_information' => [],
                'translation_keys' => [
                    'title' => '',
                    'description' => '',
                ],
            ],
        ];
    }

    /**
     * Set mock user service to container
     *
     * @param int $userId Id for user to get
     *
     * @return void
     */
    public function setMockUserService(int $userId): void
    {
        $mockUserService = $this->container->createMock(UserServiceInterface::class);
        $this->container->set(UserServiceInterface::class, $mockUserService);
        $mockUserService->expects($this->any())->method('getUserById')->with($userId)
          ->willReturn($this->getMockUser($userId));
    }

    /**
     * Get a FinnaResourceList used for testing
     *
     * @return FinnaResourceList
     */
    public function getResourceList(): FinnaResourceList
    {
        $mockedLaminasAdapter = $this->container->createMock(Adapter::class);
        $this->container->set(Adapter::class, $mockedLaminasAdapter);
        $factory = new RowGatewayFactory();
        $list = $factory($this->container, FinnaResourceList::class);
        $mockedDbServiceManager = $this->container->createMock(PluginManager::class, ['get']);
        $mockedDbServiceManager->expects($this->any())->method('get')->with(UserServiceInterface::class)->willReturn(
            $this->container->get(UserServiceInterface::class)
        );
        $list->setDbServiceManager($mockedDbServiceManager);
        return $list;
    }

    /**
     * Get a reservation list service for testing
     *
     * @param string $fixture Path to download a test ReservationList.yaml file
     *
     * @return ReservationListService
     */
    public function getReservationListService(string $fixture): ReservationListService
    {
        $config = \Symfony\Component\Yaml\Yaml::parse($this->getFixture($fixture, 'Finna'));
        $mockYamlReader = $this->container->createMock(\Finna\Config\YamlReader::class, ['getFinna']);
        $mockYamlReader->expects($this->any())->method('getFinna')
          ->with('ReservationList.yaml', 'config/finna')->willReturn($config);
        $this->container->set(\Finna\Config\YamlReader::class, $mockYamlReader);

        $factory = new ReservationListServiceFactory();
        return $factory($this->container, ReservationListService::class);
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
     * @return       void
     * @dataProvider getProperListData
     */
    public function testListCreation(int $id, array $prefill): void
    {
        $this->setMockUserService($id);
        $this->setContainer();
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($id);
        $newList = $service->createListForUser($user, $prefill);
        $this->assertEquals($id, $newList->getUser()->getId());
    }

    /**
     * Test failing list creation
     *
     * @param int   $id      User id
     * @param array $prefill Array to prefill the list with
     *
     * @return       void
     * @dataProvider getFailingListData
     */
    public function testFailingListCreation(int $id, array $prefill): void
    {
        $this->setMockUserService($id);
        $this->setContainer();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing values to populate list');
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($id);
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
     * @return       void
     * @dataProvider getUserAccessSuccessData
     */
    public function testUserAccessSuccess(int $ownerId, int $currentId): void
    {
        $this->setMockUserService($ownerId);
        $this->setContainer();
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($ownerId);
        $newList = $service->createListForUser($user);
        $currentUser = $this->getMockUser($currentId);
        $this->assertEquals(true, $service->userCanEditList($currentUser, $newList));
    }

    /**
     * Test user list access failure
     *
     * @param int  $ownerId   Owner id for the list
     * @param ?int $currentId Current user id for the list or null for no user
     *
     * @return       void
     * @dataProvider getUserAccessFailureData
     */
    public function testUserAccessFailure(int $ownerId, ?int $currentId = null): void
    {
        $this->setMockUserService($ownerId);
        $this->setContainer();
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($ownerId);
        $newList = $service->createListForUser($user);
        $currentUser = $currentId ? $this->getMockUser($currentId) : null;
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
     * @return       void
     * @dataProvider getTestDeletingListData
     */
    public function testDeletingList(int $ownerId, ?int $currentId, string $expected): void
    {
        $this->setMockUserService($ownerId);
        $this->setContainer();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($expected);
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($ownerId);
        $newList = $service->createListForUser($user);
        $currentUser = $currentId ? $this->getMockUser($currentId) : null;
        $service->destroyList($newList, $currentUser);
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
     * @return       void
     * @dataProvider getTestSettingListOrderedData
     */
    public function testSettingListOrdered(int $ownerId, array $data, array $expected): void
    {
        $this->setMockUserService($ownerId);
        $this->setContainer();
        if (!isset($data['pickup_date'])) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Missing pickup date');
        }
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $user = $this->getMockUser($ownerId);
        $newList = $service->createListForUser($user);
        $service->setListOrdered($user, $newList, $data);
        $this->assertEquals($expected['connection'], $newList->getConnection());
        $this->assertEquals($expected['external_id'] ?? null, $newList->getExternalId());
        if (isset($data['pickup_date'])) {
            $this->assertEquals(
                true,
                $newList->getPickupDate()->format('Y-m-d'),
                (string)$expected['pickup_date']->format('Y-m-d')
            );
        }
    }

    /**
     * Test reading list properties
     *
     * @param array  $params   Parameters to pass for finding a list with properties
     * @param string $fixture  Path to the fixture which holds data for testing the list properties
     * @param array  $expected Expected results
     *
     * @return       void
     * @dataProvider getListPropertyData
     */
    public function testListProperties(array $params, string $fixture, array $expected): void
    {
        $this->setContainer();
        $service = $this->getReservationListService($fixture);
        $listProperties = $service->getListProperties(...$params);
        $this->assertEquals(
            $expected,
            $listProperties
        );
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
              'firstName' => 'Testaaja',
              'lastName' => 'von Testaaja',
              'email' => 'testaaja@testeri.fi',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
              'listId' => null,
              'institution' => 'Example Institution',
              'listIdentifier' => 'list_with_email',
              'firstName' => 'Testaaja',
              'lastName' => 'von Testaaja',
              'email' => 'testaaja@testeri.fi',
              'record_ids_text' => '',
              'record_source_and_ids' => [],
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
              'phone' => null,
            ],
          ],
          'different name given' => [
            'Example Institution',
            'list_with_email',
            [
              'firstName' => 'Pouta',
              'lastName' => 'Pakkanen',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
              'listId' => null,
              'institution' => 'Example Institution',
              'listIdentifier' => 'list_with_email',
              'firstName' => 'Pouta',
              'lastName' => 'Pakkanen',
              'email' => 'testaaja@testeri.fi',
              'record_ids_text' => '',
              'record_source_and_ids' => [],
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
              'phone' => null,
            ],
          ],
          'disec get list values' => [
            'Example Institution',
            'list_with_disec',
            [
              'firstName' => 'Pouta',
              'lastName' => 'Pakkanen',
              'pickup_date' => '2025-01-01',
              'message' => 'Test message',
            ],
            [
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
              'phone' => null,
            ],
          ],
        ];
    }

    /**
     * Creates an instance of a plugin manager for getting connection handlers
     *
     * @return HandlerPluginManager
     */
    public function getPluginManager(): HandlerPluginManager
    {
        $factory = new \VuFind\ServiceManager\AbstractPluginManagerFactory();
        return $factory($this->container, HandlerPluginManager::class);
    }

    /**
     * Test list value returns for handlers
     *
     * @param string $institution    Institution for list
     * @param string $listIdentifier List identifier for list
     * @param array  $requestValues  Request values to test
     * @param array  $expected       Expected values to be returned
     *
     * @return       void
     * @dataProvider getTestHandlerData
     */
    public function testHandlers(
        string $institution,
        string $listIdentifier,
        array $requestValues,
        array $expected
    ): void {
        $this->setMockUserService(1);
        $this->setContainer();
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $pluginManager = $this->getPluginManager();
        $listProperties = $service->getListProperties($institution, $listIdentifier)['properties'];
        $handler = $pluginManager->getWithConfig($listProperties);
        $user = $this->getMockUser(1);
        $list = $service->createListForUser($user, [
          'title' => 'Test List Title',
          'desc' => 'Test List Desc',
          'institution' => $institution,
          'listIdentifier' => $listIdentifier,
          'connection' => $listProperties['Connection']['type'],
        ]);
        $returned = $handler->getValuesForListOrder($list, $user, $requestValues);
        $this->assertEquals($expected, $returned);
    }

    /**
     * Get a disec handler with modified container
     *
     * @return Disec
     */
    public function getDisecHandler(): Disec
    {
        $mockLaminasResponse = $this->container->createMock(
            \Laminas\Http\Response::class,
            [
            'isSuccess',
            'getBody',
            ]
        );
        $mockLaminasResponse->expects($this->any())->method('isSuccess', 'getBody')->willReturn(true);
        $mockLaminasResponse->expects($this->any())->method('getBody')->willReturn('{"id":123123}');
        $mockLaminasClient = $this->container->createMock(
            \Laminas\Http\Client::class,
            [
            'setHeaders',
            'setMethod',
            'setRawBody',
            'send',
            ]
        );
        $mockLaminasClient->expects($this->any())->method('send')->willReturn($mockLaminasResponse);
        $mockHttpService = $this->container->createMock(\VuFindHttp\HttpService::class, ['createClient']);
        $mockHttpService->expects($this->any())->method('createClient')->willReturn($mockLaminasClient);
        $this->container->set(\VuFindHttp\HttpService::class, $mockHttpService);
        $factory = new HandlerFactory();
        return $factory($this->container, Disec::class);
    }

    /**
     * Get an email handler with modified container
     *
     * @return Email
     */
    public function getEmailHandler(): Email
    {
        $mockViewRenderer = $this->container->createMock(\Laminas\View\Renderer\PhpRenderer::class, ['render']);
        $mockViewRenderer->expects($this->any())->method('render')->willReturn('test email message');
        $this->container->set('ViewRenderer', $mockViewRenderer);
        $factory = new HandlerFactory();
        return $factory($this->container, Email::class);
    }

    /**
     * Test disec data sending
     *
     * @return void
     */
    public function testDisecPlaceOrder(): void
    {
        $this->setMockUserService(1);
        $this->setContainer();
        $this->getPluginManager();
        $user = $this->getMockUser(1);
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $handler = $this->getDisecHandler();
        $testValues = [
          'listId' => null,
          'institution' => 'Example Institution',
          'listIdentifier' => 'list_with_email',
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
        $this->setMockUserService(1);
        $this->setContainer();
        $this->getPluginManager();
        $user = $this->getMockUser(1);
        $service = $this->getReservationListService('reservationlist/ReservationList.yaml');
        $handler = $this->getEmailHandler();
        $testValues = [
          'listId' => null,
          'institution' => 'Example Institution',
          'listIdentifier' => 'list_with_email',
          'firstName' => 'Pouta',
          'lastName' => 'Pakkanen',
          'email' => 'testaaja@testeri.fi',
          'record_ids_text' => '',
          'record_source_and_ids' => [],
          'pickup_date' => '2025-01-01',
          'message' => 'Test message',
        ];
        $handler->init([
        'Recipient' => [
          [
            'name' => 'test',
            'email' => 'test@email.fi',
          ],
        ],
        'Connection' => [
          'Sender' => [
            'name' => 'testisender',
            'email' => 'testisender@email.fi',
          ],
          'Subject' => 'test subject',
        ],
        ]);
        $result = $handler->placeOrder($testValues, $user);
        $this->assertEquals([
          'external_id' => null,
          'connection' => 'email',
          'pickup_date' => '2025-01-01',
          'success' => true,
        ], $result);
    }
}
