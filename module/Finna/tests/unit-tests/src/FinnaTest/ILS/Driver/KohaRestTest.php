<?php

/**
 * KohaRest test class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\ILS\Driver;

use Finna\ILS\Driver\KohaRest;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Service\CurrencyFormatter;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * KohaRest test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class KohaRestTest extends \PHPUnit\Framework\TestCase
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
     * @return MockObject&KohaRest
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&KohaRest {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(KohaRest::class)->setConstructorArgs(
            [
                new \VuFind\Date\Converter(),
                fn ($namespace) => null,
                $this->getMockBuilder(CurrencyFormatter::class)->disableOriginalConstructor()->getMock(),
            ]
        )->onlyMethods(['makeRequest', 'debug'])
            ->getMock();
        $connector->expects($this->any())->method('makeRequest')->willReturnMap($requestMap);
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
            'email' => 'test@email.fi',
            'messagingServices' => [
                'Item_Due' => [
                    'type' => 'dueDateNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
                'Advance_Notice' => [
                    'type' => 'dueDateAlert',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'days_in_advance' => [
                            'type' => 'select',
                            'value' => 5,
                            'options' => [
                                0 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                1 => [
                                    'name' => 'messaging_settings_num_of_days',
                                    'active' => false,
                                ],
                                2 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
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
                                    'active' => true,
                                ],
                                6 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                7 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                8 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                9 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                10 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                11 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                12 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                13 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                14 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                15 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                16 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                17 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                18 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                19 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                20 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                21 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                22 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                23 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                24 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                25 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                26 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                27 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                28 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                29 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                                30 => [
                                    'name' => 'messaging_settings_num_of_days_plural',
                                    'active' => false,
                                ],
                            ],
                            'readonly' => false,
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
                'Hold_Filled' => [
                    'type' => 'pickUpNotice',
                    'settings' => [
                        'transport_types' => [
                            'type' => 'multiselect',
                            'options' => [
                                'sms' => [
                                    'active' => false,
                                ],
                                'letter' => [
                                    'active' => true,
                                ],
                            ],
                        ],
                        'digest' => [
                            'type' => 'boolean',
                            'active' => true,
                            'readonly' => true,
                        ],
                    ],
                ],
            ],
            'loan_history' => 'true',
            'firstname' => 'Test',
            'lastname' => 'Tester',
            'address1' => 'Address 1',
            'address2' => 'Address 2',
            'city' => 'Test city',
            'country' => 'Test country',
            'zip' => '010101',
            'phone' => '0101010101',
            'mobile_phone' => null,
            'expiration_date' => '01-19-2022',
            'group' => null,
            'home_library' => null,
            'birthdate' => '',
            'calling_name' => '',
            'category' => '4',
            'expiration_soon' => true,
            'expired' => false,
            'hold_identifier' => 'Other name',
            'guarantors' => [
                [
                    'firstname' => 'quarantor1',
                    'lastname' => 'surname1',
                ],
            ],
            'guarantees' => [
                [
                    'firstname' => 'quarantee1',
                    'lastname' => 'surname_qua_1',
                ],
            ],
            'notes' => 'Test opac notes',
            'messages' => [
                [
                    'date' => '05-15-2005',
                    'library' => 'Test library 5',
                    'message' => 'Library message',
                ],
                [
                    'date' => '01-11-2009',
                    'library' => '',
                    'message' => 'Another library message',
                ],
            ],
            'full_data' => [],
            'smsnumber' => '0123456789',
        ];
        yield 'profile all values set' => [
            'koharest/profile.json',
            $defaultExpected,
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'koharest/profile_partial.json',
            [
                'calling_name' => '',
                'category' => '',
                'expiration_soon' => false,
                'expired' => false,
                'hold_identifier' => 'Other name',
                'guarantors' => [],
                'guarantees' => [],
                'notes' => '',
                'messages' => [],
                'smsnumber' => null,
                'messagingServices' => [],
                'loan_history' => '',
                'email' => '',
                'firstname' => '',
                'lastname' => 'Tester',
                'birthdate' => '',
                'address1' => '',
                'address2' => 'Address 2',
                'city' => '',
                'country' => '',
                'zip' => '',
                'phone' => null,
                'mobile_phone' => null,
                'expiration_date' => '',
                'group' => null,
                'home_library' => null,
            ],
            [
                'updateMessagingSettings' => [
                    'method' => 'driver',
                ],
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
        $profileResponse = $this->getJsonFixture($fixtureKey, 'Finna');
        $requestMap = [
            [
                [
                    'path' => ['v1', 'contrib', 'kohasuomi', 'patrons', '5'],
                    'query' => [
                        'query_blocks' => 1,
                        'query_relationships' => 1,
                        'query_messaging_preferences' => 1,
                        'query_messages' => 1,
                    ],
                ],
                $profileResponse,
            ],
            [
                'v1/libraries?_per_page=-1',
                $this->getJsonFixture('koharest/libraries.json', 'Finna'),
            ],
        ];
        $expected['full_data'] = $profileResponse['data'];
        $connector = $this->createConnector(requestMap: $requestMap, config: $config);
        $profile = $connector->getMyProfile(['cat_username' => '1111', 'cat_password' => '2222', 'id' => '5']);
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
            'koharest/patron.json',
            [
                'id' => '12345',
                'email' => 'test@email.fi',
                'firstname' => 'Test',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
                'home_library' => '5',
            ],
        ];
        yield 'patron some values missing' => [
            'koharest/patron_partial.json',
            [
                'id' => '12345',
                'email' => '',
                'firstname' => '',
                'lastname' => '',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '2222',
                'home_library' => '',
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
        $requestMap = [
            [
                [
                    'path' => 'v1/contrib/kohasuomi/auth/patrons/validation',
                    'json' => ['userid' => '1111', 'password' => '2222'],
                    'method' => 'POST',
                    'errors' => true,
                ],
                $this->getJsonFixture($fixtureKey, 'Finna'),
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap);
        $patron = $connector->patronLogin('1111', '2222');
        $this->assertEquals($expected, $patron);
    }
}
