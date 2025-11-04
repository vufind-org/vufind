<?php

/**
 * AxiellWebServices test class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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

namespace FinnaTest\ILS\Driver;

use Finna\ILS\Driver\AxiellWebServices;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\PathResolver;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * AxiellWebServices test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AxiellWebServicesTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ReflectionTrait;

    /**
     * Local cache
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Create connector
     *
     * @param array $requestMap Request map
     * @param array $config     Driver config
     *
     * @return MockObject&AxiellWebServices
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&AxiellWebServices {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(AxiellWebServices::class)->setConstructorArgs(
            [
                new \VuFind\Date\Converter(),
                $this->getMockBuilder(PathResolver::class)->disableOriginalConstructor()->getMock(),
            ]
        )->onlyMethods(['doSOAPRequest', 'authenticatePatron', 'debug', 'putCachedData', 'getCachedData'])
            ->getMock();
        $connector->expects($this->any())->method('doSOAPRequest')->willReturnMap($requestMap);
        $connector->expects($this->any())->method('putCachedData')->willReturnCallback(
            fn ($key, $entry) => $this->cache[$key] = $entry
        );
        $connector->expects($this->any())->method('getCachedData')->willReturnCallback(
            fn ($key) => $this->cache[$key] ?? null
        );
        $connector->setConfig($config);
        return $connector;
    }

    /**
     * Data provider for testGetMyProfile
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): Generator
    {
        $defaultExpected = [
            'emailId' => '124213',
            'addressId' => '13123',
            'phoneId' => '3',
            'phoneLocalCode' => '1231231',
            'phoneAreaCode' => '222',
            'patronId' => '010101',
            'id' => '121212',
            'cat_username' => '1111',
            'cat_password' => '2222',
            'email' => 'active@email.fi',
            'messagingServices' => [
                'overdueNotice' => [
                    'type' => 'overdueNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'select',
                            'options' => [
                                'email' => [
                                    'active' => false,
                                ],
                                'print' => [
                                    'active' => true,
                                ],
                                'sms' => [
                                    'active' => false,
                                ],
                            ],
                            'value' => 'print',
                        ],
                    ],
                ],
                'dueDateAlert' => [
                    'type' => 'dueDateAlert',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'select',
                            'options' => [
                                'email' => [
                                    'active' => false,
                                ],
                                'inactive' => [
                                    'active' => true,
                                ],
                                'sms' => [
                                    'active' => false,
                                ],
                            ],
                            'value' => 'inactive',
                        ],
                        'days_in_advance' => [
                            'type' => 'select',
                            'value' => 2,
                            'options' => [
                                1 => [
                                    'name' => 'messaging_settings_num_of_days',
                                    'active' => false,
                                ],
                                2 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => true,
                                ],
                                3 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                4 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                5 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                            ],
                            'readonly' => false,
                        ],
                    ],
                ],
                'pickUpNotice' => [
                    'type' => 'pickUpNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'select',
                            'options' => [
                                'email' => [
                                    'active' => false,
                                ],
                                'print' => [
                                    'active' => false,
                                ],
                                'sms' => [
                                    'active' => true,
                                ],
                            ],
                            'value' => 'sms',
                        ],
                    ],
                ],
            ],
            'loan_history' => 'true',
            'firstname' => 'Testi',
            'lastname' => 'Tester',
            'address1' => 'Active street 1',
            'address2' => null,
            'city' => 'Act',
            'country' => 'Ive',
            'zip' => '02010',
            'phone' => '2221231231',
            'mobile_phone' => null,
            'expiration_date' => null,
            'group' => null,
            'home_library' => null,
            'birthdate' => '',
        ];
        yield 'profile all values set' => [
            'axiellwebservices/profile.xml',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
            ],
        ];
        $legacyMessagingServices = [
            'pickUpNotice' => [
                'type' => 'pickUpNotice',
                'settings' => [
                    'transport_types' => [
                        'type' => 'select',
                        'options' => [
                            'print' => [
                                'active' => false,
                            ],
                            'email' => [
                                'active' => false,
                            ],
                            'sms' => [
                                'active' => true,
                            ],
                            'inactive' => [
                                'active' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'overdueNotice' => [
                'type' => 'overdueNotice',
                'settings' => [
                    'transport_types' => [
                        'type' => 'select',
                        'options' => [
                            'print' => [
                                'active' => true,
                            ],
                            'email' => [
                                'active' => false,
                            ],
                            'sms' => [
                                'active' => false,
                            ],
                            'inactive' => [
                                'active' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'dueDateAlert' => [
                'type' => 'dueDateAlertEmail',
                'settings' => [
                    'transport_types' => [
                        'type' => 'select',
                        'options' => [
                            'email' => [
                                'active' => false,
                            ],
                            'inactive' => [
                                'active' => true,
                            ],
                        ],
                    ],
                    'days_in_advance' => [
                        'type' => 'select',
                        'options' => [
                            1 => [
                                'name' => 'messaging_settings_num_of_days',
                                'active' => false,
                            ],
                            2 => [
                                'name' => 'messaging_settings_num_of_days_plural',
                                'active' => true,
                            ],
                            3 => [
                                'name' => 'messaging_settings_num_of_days_plural',
                                'active' => false,
                            ],
                            4 => [
                                'name' => 'messaging_settings_num_of_days_plural',
                                'active' => false,
                            ],
                            5 => [
                                'name' => 'messaging_settings_num_of_days_plural',
                                'active' => false,
                            ],
                        ],
                        'value' => '2',
                        'readonly' => false,
                    ],
                ],
            ],
        ];
        $defaultExpected['messagingServices'] = $legacyMessagingServices;
        yield 'legacy messaging settings' => [
            'axiellwebservices/profile.xml',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'database',
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'axiellwebservices/profile_partial.xml',
            [
                'emailId' => null,
                'addressId' => null,
                'phoneId' => null,
                'phoneLocalCode' => null,
                'phoneAreaCode' => null,
                'patronId' => '010101',
                'id' => '121212',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'messagingServices' => [],
                'loan_history' => null,
                'firstname' => '',
                'lastname' => 'Tester',
                'birthdate' => '',
                'address1' => null,
                'address2' => null,
                'city' => null,
                'country' => null,
                'zip' => null,
                'phone' => null,
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => null,
                'home_library' => null,
                'email' => null,
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param string $fixtureKey Request fixture path
     * @param array  $expected   Expected results for the test
     * @param array  $config     Driver specific configuration
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(string $fixtureKey, array $expected, array $config = []): void
    {
        $conf = [
            'arenaMember' => '',
            'user' => '1111',
            'password' => '2222',
            'language' => 'en',
        ];
        $requestMap = [
            [
                '',
                'getPatronInformation',
                'patronInformationResult',
                '1111',
                ['patronInformationParam' => $conf],
                $this->loadStdObjectResponse($fixtureKey),
            ],
            [
                'aurora.url',
                'getMessageServices',
                'messageServicesResponse',
                '1111',
                ['messageServicesRequest' => $conf],
                $this->loadStdObjectResponse('axiellwebservices/message_services.xml'),
            ],
        ];

        $connector = $this->createConnector(requestMap: $requestMap, config: $config);
        $connector->expects($this->any())->method('authenticatePatron')->willReturn('010101');
        $this->setProperty($connector, 'patronaurora_wsdl', 'aurora.url');
        $profile = $connector->getMyProfile(['cat_username' => '1111', 'cat_password' => '2222']);
        $this->assertEquals($expected, $profile);
    }

    /**
     * Data provider for testPatronLogin
     *
     * @return Generator
     */
    public static function getTestPatronLoginData(): Generator
    {
        yield 'patron all values set' => [
            'axiellwebservices/patron.xml',
            [
                'patronId' => '010101',
                'id' => '010101',
                'email' => null,
                'firstname' => 'Testi',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
            ],
        ];
        yield 'patron some values missing' => [
            'axiellwebservices/patron_partial.xml',
            [
                'patronId' => '010101',
                'id' => '010101',
                'email' => null,
                'firstname' => '',
                'lastname' => 'Tester',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '2222',
            ],
        ];
    }

    /**
     * Test patronLogin
     *
     * @param string $fixtureKey Response fixture
     * @param array  $expected   Expected results for the test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPatronLoginData')]
    public function testPatronLogin(string $fixtureKey, array $expected): void
    {
        $conf = [];
        $conf['patronInformationParam'] = [
            'arenaMember' => '',
            'user' => '1111',
            'password' => '2222',
            'language' => 'en',
        ];
        $requestMap = [
            [
                '',
                'getPatronInformation',
                'patronInformationResult',
                '1111',
                $conf,
                $this->loadStdObjectResponse($fixtureKey),
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap);
        $connector->expects($this->any())->method('authenticatePatron')->willReturn('010101');
        $patron = $connector->patronLogin('1111', '2222');
        $this->assertEquals($expected, $patron);
    }

    /**
     * Load fixture and convert it into an stdObject
     *
     * @param string $fixture Fixture path
     *
     * @return object
     */
    protected function loadStdObjectResponse(string $fixture): object
    {
        $loaded = simplexml_load_string($this->getFixture($fixture, 'Finna'));
        return json_decode(json_encode($loaded));
    }
}
