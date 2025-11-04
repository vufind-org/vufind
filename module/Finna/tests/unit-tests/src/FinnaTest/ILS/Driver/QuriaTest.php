<?php

/**
 * Quria test class
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

use Finna\ILS\Driver\Quria;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Config\PathResolver;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Quria test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class QuriaTest extends \PHPUnit\Framework\TestCase
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
     * @return MockObject&Quria
     */
    public function createConnector(
        array $requestMap = [],
        array $config = [],
    ): MockObject&Quria {
        // Clear the cache
        $this->cache = [];
        $connector = $this->getMockBuilder(Quria::class)->setConstructorArgs(
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
            'patronId' => '010101',
            'id' => '121212',
            'cat_username' => '1111',
            'cat_password' => '2222',
            'email' => 'active@email.fi',
            'addressId' => '13123',
            'messagingServices' => [],
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
            'email_0' => 'notactive@email.fi',
            'email_0_id' => '221',
            'email_0_active' => false,
            'email_1' => 'active@email.fi',
            'email_1_id' => '124213',
            'email_1_active' => true,
            'phone_0' => '1215125125',
            'phone_0_id' => '123',
            'phone_0_active' => false,
            'phone_1' => '2221231231',
            'phone_1_id' => '3',
            'phone_1_active' => true,
        ];
        yield 'profile all values set' => [
            'quria/profile.xml',
            $defaultExpected,
        ];
        yield 'profile some values missing' => [
            'quria/profile_partial.xml',
            [
                'firstname' => 'Tester',
                'lastname' => '',
                'address1' => null,
                'address2' => null,
                'city' => null,
                'country' => null,
                'zip' => null,
                'phone' => '1215125125',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => null,
                'email_0' => 'notactive@email.fi',
                'email_0_id' => '221',
                'email_0_active' => false,
                'phone_0' => '1215125125',
                'phone_0_id' => '123',
                'phone_0_active' => false,
                'addressId' => null,
                'id' => '121212',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'patronId' => '010101',
                'loan_history' => null,
                'email' => 'notactive@email.fi',
                'birthdate' => '',
                'home_library' => null,
                'messagingServices' => [],
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
                'getPatronStatus',
                'patronStatusResult',
                '1111',
                ['patronStatusParam' => $conf],
                $this->loadStdObjectResponse('quria/patron_status.xml'),
            ],
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
                $this->loadStdObjectResponse('quria/message_services.xml'),
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
        // Quria uses the same request for patron and profile data
        yield 'patron all values set' => [
            'quria/profile.xml',
            [
                'patronId' => '010101',
                'id' => '121212',
                'email' => 'active@email.fi',
                'firstname' => 'Testi',
                'lastname' => 'Tester',
                'cat_username' => '1111',
                'cat_password' => '2222',
                'major' => null,
                'college' => null,
            ],
        ];
        yield 'patron some values missing' => [
            'quria/profile_partial.xml',
            [
                'patronId' => '010101',
                'id' => '121212',
                'email' => 'notactive@email.fi',
                'firstname' => 'Tester',
                'lastname' => '',
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
        $conf = [
            'arenaMember' => '',
            'user' => '1111',
            'password' => '2222',
            'language' => 'en',
        ];
        $requestMap = [
            [
                '',
                'getPatronStatus',
                'patronStatusResult',
                '1111',
                ['patronStatusParam' => $conf],
                $this->loadStdObjectResponse('quria/patron_status.xml'),
            ],
            [
                '',
                'getPatronInformation',
                'patronInformationResult',
                '1111',
                ['patronInformationParam' => $conf],
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
