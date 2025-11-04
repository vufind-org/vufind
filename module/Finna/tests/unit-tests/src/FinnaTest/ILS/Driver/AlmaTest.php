<?php

/**
 * Alma test class
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

use Finna\ILS\Driver\Alma;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use SimpleXMLElement;
use VuFind\I18n\TranslatableString;

/**
 * Alma test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AlmaTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get data for update address test
     *
     * @return Generator
     */
    public static function getTestUpdateAddressData(): Generator
    {
        yield 'single entries' => [
            [
                'id' => '123456',
            ],
            [
                'address1' => 'Test address update',
                'email' => 'newemail@email.fi',
                'phone' => '0123456789',
            ],
            [
                'updateProfile' => [
                    'fields' => [
                        'Address:address1',
                        'Email:email',
                        'Phone:phone',
                    ],
                ],
            ],
            'alma/user.xml',
            'alma/user_address_updated.xml',
        ];

        yield 'empty entries' => [
            [
                'id' => '123456',
            ],
            [
                'address1' => '',
                'email' => '',
                'phone' => '',
            ],
            [
                'updateProfile' => [
                    'fields' => [
                        'Address:address1',
                        'Email:email',
                        'Phone:phone',
                    ],
                ],
            ],
            'alma/user.xml',
            'alma/user_empty_address_updated.xml',
        ];

        yield 'multiple addresses' => [
            [
                'id' => '123456',
            ],
            [
                'addresses' => [
                    'address' => [
                        'address1' => 'Test address 1',
                        'address2' => 'Test address 2',
                    ],
                ],
            ],
            [
                'updateProfile' => [
                    'fields' => [
                        'Address:address1',
                        'Address2:address2',
                        'Email:email',
                        'Phone:phone',
                    ],
                ],
            ],
            'alma/user_multiple_address.xml',
            'alma/user_multiple_address_updated.xml',
        ];
    }

    /**
     * Test updating address
     *
     * @param array  $patron          User patron
     * @param array  $details         Update details
     * @param array  $config          Driver field config
     * @param string $userFixture     Path to user fixture
     * @param string $expectedFixture Path to expected fixture
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestUpdateAddressData')]
    public function testUpdateAddress(
        array $patron,
        array $details,
        array $config,
        string $userFixture,
        string $expectedFixture
    ): void {
        $userXml = $this->getFixture($userFixture, 'Finna');
        $expectedXml = $this->getFixture($expectedFixture, 'Finna');
        $expectedXmlObject = new SimpleXMLElement($expectedXml);
        $makeRequestMap = [
            ['/users/' . $patron['id'], 'GET', new SimpleXMLElement($userXml)],
            ['/users/' . $patron['id'], 'PUT', ['', 200], 'expected' => $expectedXmlObject->asXML()],
        ];
        $driver = $this->getDriver($makeRequestMap);
        $driver->setConfig($config);
        $driver->updateAddress($patron, $details);
    }

    /**
     * Data provider for testPatronLogin
     *
     * @return Generator
     */
    public static function getTestPatronLoginData(): Generator
    {
        yield 'patron all values set' => [
            'alma/patron.xml',
            [
                'id' => '57391',
                'cat_username' => '1111',
                'cat_password' => '2121',
                'firstname' => 'John',
                'lastname' => 'Smith',
                'email' => 'pref@email.if',
                'major' => null,
                'college' => null,
            ],
        ];
        yield 'patron some values missing' => [
        'alma/patron_partial.xml',
            [
                'id' => '57391',
                'email' => null,
                'firstname' => 'Test',
                'lastname' => '',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '2121',
            ],
        ];
    }

    /**
     * Test patron login
     *
     * @param string $fixtureKey Fixture key
     * @param array  $expected   Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPatronLoginData')]
    public function testPatronLogin(string $fixtureKey, array $expected): void
    {
        $userFixture = $this->getFixture($fixtureKey, 'Finna');
        $makeRequestMap = [
            ['/users/1111', 'GET', [new SimpleXMLElement($userFixture), 200]],
        ];
        $driver = $this->getDriver($makeRequestMap);
        $result = $driver->patronLogin('1111', '2121');
        $this->assertEquals(
            $expected,
            $result
        );
    }

    /**
     * Data provider for testGetMyProfileData
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): Generator
    {
        $fullProfile = [
            'barcode' => '01924019240',
            'email' => 'pref@email.if',
            'group_code' => 'test',
            'expired' => null,
            'expiration_soon' => null,
            'self_service_pin' => null,
            'address3' => 'Line 3',
            'homeAddress' => 'Line 1, 00000 City',
            'workAddress' => 'A street 1, 00000 Far away',
            'account_type' => 'normal',
            'language' => 'fi',
            'firstname' => 'John',
            'lastname' => 'Smith',
            'birthdate' => '',
            'address1' => 'Line 1',
            'address2' => 'Line 2',
            'city' => 'City',
            'country' => new TranslatableString('Country', ''),
            'zip' => '00000',
            'phone' => '9876543210',
            'mobile_phone' => null,
            'home_library' => null,
            'expiration_date' => null,
            'group' => 'descgroup',
            'addresses' => [
                [
                    'address1' => 'A street 1',
                    'address2' => '          ',
                    'address3' => 'Not a default field',
                    'country' => new TranslatableString('Far', ''),
                    'city' => 'Far away',
                    'zip' => '00000',
                    'types' => [
                        'work',
                        'something',
                    ],
                    'preferred' => false,
                ],
                [
                    'address1' => 'Line 1',
                    'address2' => 'Line 2',
                    'address3' => 'Line 3',
                    'country' => new TranslatableString('Country', ''),
                    'city' => 'City',
                    'zip' => '00000',
                    'types' => [
                        'Type 1',
                        'home',
                    ],
                    'preferred' => true,
                ],
            ],
            'guarantees' => [
                ['lastname' => 'Tester Test'],
                ['lastname' => 'Ttee Tst'],
            ],
            'messagingServices' => [],
            'loan_history' => null,
        ];
        $fullProfileNonPreferredEmail = $fullProfile;
        $fullProfileNonPreferredEmail['email'] = 'first@email.if';

        yield 'profile all values set' => [
            'alma/profile.xml',
            $fullProfile,
        ];
        yield 'profile all values set but no preferred email' => [
            'alma/profile_no_preferred_email.xml',
            $fullProfileNonPreferredEmail,
        ];
        yield 'profile some values missing' => [
            'alma/profile_partial.xml',
            [
                'addresses' => [],
                'barcode' => null,
                'group_code' => null,
                'expired' => null,
                'expiration_soon' => null,
                'self_service_pin' => null,
                'address3' => null,
                'homeAddress' => null,
                'workAddress' => null,
                'guarantees' => [],
                'account_type' => '',
                'language' => null,
                'messagingServices' => [],
                'loan_history' => null,
                'email' => null,
                'firstname' => null,
                'lastname' => null,
                'birthdate' => '',
                'address1' => null,
                'address2' => null,
                'city' => null,
                'country' => '',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => null,
                'home_library' => null,
                'zip' => null,
                'phone' => null,
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param string $fixtureKey Fixture key
     * @param array  $expected   Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(string $fixtureKey, array $expected): void
    {
        $profileFixture = $this->getFixture($fixtureKey, 'Finna');
        $makeRequestMap = [
            ['/users/1111', 'GET', new SimpleXMLElement($profileFixture)],
        ];
        $driver = $this->getDriver($makeRequestMap);
        $result = $driver->getMyProfile(['id' => '1111']);

        $this->assertEquals(
            $expected,
            $result
        );
    }

    /**
     * Get mocked alma record driver
     *
     * @param array $makeRequestMap Map for requests in makeRequest function.
     *                              0 => url, 1 => method, 2 => response, expected => if assertEquals is required
     *
     * @return MockObject
     */
    public function getDriver(array $makeRequestMap = []): MockObject
    {
        $driver = $this->getMockBuilder(Alma::class)->onlyMethods(['makeRequest'])
          ->disableOriginalConstructor()->getMock();
        $driver->expects($this->any())->method('makeRequest')->willReturnCallback(
            function (
                $url,
                $getP = [],
                $postP = [],
                $method = 'GET',
                $rawBody = null,
                $headers = null,
                $allowedErrors = [],
                $returnStatus = false
            ) use ($makeRequestMap) {
                $foundRequest = [];
                foreach ($makeRequestMap as $request) {
                    if (
                        $request[0] === $url
                        && $request[1] === $method
                    ) {
                        if ($method === 'PUT') {
                            $this->assertEquals($request['expected'], $rawBody);
                        }
                        $foundRequest = $request[2];
                        break;
                    }
                }
                return $foundRequest;
            }
        );
        return $driver;
    }
}
