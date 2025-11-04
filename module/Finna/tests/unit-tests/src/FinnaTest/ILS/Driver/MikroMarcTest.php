<?php

/**
 * MikroMarc test class
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

use Finna\ILS\Driver\Mikromarc;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * MikroMarc test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MikroMarcTest extends \PHPUnit\Framework\TestCase
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
     * @return MockObject&MikroMarc
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&MikroMarc {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(Mikromarc::class)->setConstructorArgs(
            [
                new \VuFind\Date\Converter(),
                $this->getMockBuilder(\VuFind\I18n\Sorter::class)->disableOriginalConstructor()->getMock(),
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
                    'dueDateNotice' => [
                        'type' => 'dueDateNotice',
                        'settings' => [
                            'digest' => [
                                'type' => 'boolean',
                                'readonly' => false,
                                'active' => true,
                            ],
                        ],
                    ],
                    'checkoutNotice' => [
                        'type' => 'checkoutNotice',
                        'settings' => [
                            'transport_types' => [
                                'type' => 'select',
                                'options' => [
                                    'email' => [
                                        'active' => true,
                                    ],
                                    'print' => [
                                        'active' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'notifications' => [
                        'type' => 'notifications',
                        'settings' => [
                            'transport_types' => [
                                'type' => 'multiselect',
                                'options' => [
                                    'email' => [
                                        'active' => false,
                                    ],
                                    'sms' => [
                                        'active' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'loan_history' => null,
                'firstname' => 'Test',
                'lastname' => 'Tester',
                'address1' => 'Address 1',
                'address2' => 'Address 2',
                'city' => 'Test city',
                'country' => null,
                'zip' => '010101',
                'phone' => '0123456789',
                'mobile_phone' => null,
                'expiration_date' => '01-19-2022',
                'group' => null,
                'home_library' => null,
                'birthdate' => '',
                'blocked' => true,
                'cat_username' => '1111',
                'cat_password' => '2222',
                'id' => '5',
            ];
        yield 'profile all values set' => [
            'mikromarc/profile.json',
            $defaultExpected,
            [
                'messaging' => [
                    'checkoutNotice' => [
                        'Email:email',
                        'Paper:paper',
                    ],
                    'notifications' => [
                        'Email:email',
                        'SMS:sms',
                    ],
                ],
            ],
        ];
        yield 'profile some values missing' => [
            'mikromarc/profile_partial.json',
            [
                'blocked' => false,
                'cat_username' => '1111',
                'cat_password' => '2222',
                'id' => '5',
                'messagingServices' => [
                    'dueDateNotice' => [
                        'type' => 'dueDateNotice',
                        'settings' => [
                            'digest' => [
                                'type' => 'boolean',
                                'readonly' => false,
                                'active' => true,
                            ],
                        ],
                    ],
                ],
                'loan_history' => null,
                'email' => '',
                'firstname' => '',
                'lastname' => 'Test Some',
                'birthdate' => '',
                'address1' => '',
                'address2' => '',
                'city' => '',
                'country' => null,
                'zip' => '',
                'phone' => '0123456789',
                'mobile_phone' => null,
                'expiration_date' => '',
                'group' => null,
                'home_library' => null,
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
        $requestMap = [
            [
                ['odata', 'Borrowers(5)'],
                $this->getJsonFixture($fixtureKey, 'Finna'),
            ],
        ];
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
            'mikromarc/patron.json',
            'mikromarc/profile.json',
            [
                'id' => '5',
                'email' => 'test@email.fi',
                'firstname' => 'Test',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
                'loan_history' => null,
                'blocked' => true,
            ],
        ];
        yield 'patron some values missing' => [
            'mikromarc/patron_partial.json',
            'mikromarc/profile_partial.json',
            [
                'loan_history' => null,
                'blocked' => false,
                'id' => '5',
                'email' => '',
                'firstname' => '',
                'lastname' => 'Test Some',
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
     * @param string $fixtureKey  Response fixture
     * @param string $fixtureKey2 Response fixture 2
     * @param array  $expected    Expected results for the test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPatronLoginData')]
    public function testPatronLogin(string $fixtureKey, string $fixtureKey2, array $expected): void
    {
        $requestMap = [
            [
                ['odata', 'Borrowers', 'Default.Authenticate'],
                json_encode(
                    [
                    'Barcode' => '1111',
                    'Pin' => '2222',
                    ]
                ),
                'POST',
                true,
                $this->getJsonFixture($fixtureKey, 'Finna'),
            ],
            [
                ['odata', 'Borrowers(5)'],
                $this->getJsonFixture($fixtureKey2, 'Finna'),
            ],
        ];
        $connector = $this->createConnector(requestMap: $requestMap);
        $patron = $connector->patronLogin('1111', '2222');
        $this->assertEquals($expected, $patron);
    }
}
