<?php

/**
 * SierraRest ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Generator;
use Laminas\Session\Container;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Date\Converter;
use VuFind\ILS\Driver\SierraRest;
use VuFindTest\Feature\FixtureTrait;

/**
 * SierraRest ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SierraRestTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\ReflectionTrait;
    use FixtureTrait;

    /**
     * Test bib IDs (raw value => formatted value)
     *
     * @var array
     */
    protected $bibIds = [
        '12345' => '.b123456',
        '23456' => '.b234564',
        '34567' => '.b345672',
        '45678' => '.b456780',
        '56789' => '.b567899',
        '191456' => '.b191456x',
    ];

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $sessionFactory = function ($namespace) {
            return new \Laminas\Session\Container($namespace);
        };
        $this->driver = new SierraRest(
            new \VuFind\Date\Converter(),
            $sessionFactory
        );
    }

    /**
     * Test ID extraction.
     *
     * @return void
     */
    public function testIdExtraction()
    {
        foreach ($this->bibIds as $raw => $formatted) {
            // Extraction should return the same result whether we extract from
            // the raw value or the formatted value:
            $this->assertEquals(
                $raw,
                $this->callMethod($this->driver, 'extractBibId', [$raw])
            );
            $this->assertEquals(
                $raw,
                $this->callMethod($this->driver, 'extractBibId', [$formatted])
            );
        }
    }

    /**
     * Test default ID formatting (no prefixing).
     *
     * @return void
     */
    public function testDefaultBibFormatting()
    {
        foreach (array_keys($this->bibIds) as $id) {
            $this->assertEquals(
                $id,
                $this->callMethod($this->driver, 'formatBibId', [$id])
            );
        }
    }

    /**
     * Test default ID formatting (no prefixing).
     *
     * @return void
     */
    public function testPrefixedBibFormatting()
    {
        $this->driver->setConfig(
            ['Catalog' => ['use_prefixed_ids' => true]]
        );
        foreach ($this->bibIds as $raw => $formatted) {
            $this->assertEquals(
                $formatted,
                $this->callMethod($this->driver, 'formatBibId', [$raw])
            );
        }
    }

    /**
     * Data provider for testGetMyProfileData
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): Generator
    {
        yield 'all fields' => [
            'patron' => [
                'id' => '1111',
                'cat_password' => '1212',
            ],
            'fixture' => 'profile-response-1',
            'expected' => [
                'email' => 'preferredEmail@email.fi',
                'firstname' => 'Test',
                'lastname' => 'Testeri',
                'birthdate' => '11-1-2019',
                'address1' => 'Teststreet 251',
                'address2' => null,
                'city' => 'Testland',
                'country' => null,
                'zip' => '01234',
                'phone' => '0123456789',
                'mobile_phone' => null,
                'expiration_date' => '11-30-2201',
                'group' => null,
                'home_library' => null,
            ],
        ];
        yield 'missing some fields' => [
            'patron' => [
                'id' => '1111',
                'cat_password' => '1212',
            ],
            'fixture' => 'profile-response-2',
            'expected' => [
                'email' => null,
                'firstname' => '',
                'lastname' => '',
                'birthdate' => '',
                'address1' => 'Teststreet 251',
                'address2' => null,
                'city' => 'Testland',
                'country' => null,
                'zip' => '',
                'phone' => null,
                'mobile_phone' => null,
                'expiration_date' => '11-30-2201',
                'group' => null,
                'home_library' => null,
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param array  $patron   User patron
     * @param string $fixture  Name of the response fixture file
     * @param array  $expected Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(array $patron, string $fixture, array $expected): void
    {
        $requestMap = [
            [
                ['v6', 'patrons', $patron['id']],
                [
                    'fields' => 'default,names,emails,phones,addresses',
                ],
                'GET',
                $patron,
                $this->getJsonFixture('sierrarest/' . $fixture . '.json'),
            ],
        ];
        $driver = $this->createDriver(requestMap: $requestMap);
        $result = $driver->getMyProfile($patron);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for testPatronLogin
     *
     * @return Generator
     */
    public static function getTestPatronLoginData(): Generator
    {
        yield 'all fields' => [
            'patron' => [
                'id' => '1111',
                'cat_username' => '1111',
                'cat_password' => '1212',
            ],
            'fixture' => 'patron-response-1',
            'expected' => [
                'email' => 'pref@email.fi',
                'firstname' => 'Testaaja',
                'lastname' => 'Testeri',
                'id' => '10921',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '1212',
            ],
        ];
        yield 'missing some fields' => [
            'patron' => [
                'id' => '1111',
                'cat_username' => '1111',
                'cat_password' => '1212',
            ],
            'fixture' => 'patron-response-2',
            'expected' => [
                'email' => null,
                'firstname' => '',
                'lastname' => 'Testaaja',
                'id' => '00001',
                'major' => null,
                'college' => null,
                'cat_username' => '1111',
                'cat_password' => '1212',
            ],
        ];
    }

    /**
     * Test patronLogin
     *
     * @param array  $patron   User patron
     * @param string $fixture  Name of the response fixture file
     * @param array  $expected Expected results
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPatronLoginData')]
    public function testPatronLogin(array $patron, string $fixture, array $expected): void
    {
        $request = [
            'authMethod' => 'native',
            'patronId' => $patron['cat_username'],
            'patronSecret' => $patron['cat_password'],
        ];
        $requestMap = [
            [
                ['v6', 'patrons', 'auth'],
                json_encode($request),
                'POST',
                ['response' => 'something'],
            ],
            [
                ['v6', 'patrons', ['response' => 'something']],
                ['fields' => 'names,emails'],
                $this->getJsonFixture('sierrarest/' . $fixture . '.json'),
            ],
        ];
        $driver = $this->createDriver(requestMap: $requestMap);
        $result = $driver->patronLogin($patron['cat_username'], $patron['cat_password']);
        $this->assertEquals($expected, $result);
    }

    /**
     * Create driver
     *
     * @param ?Converter $dateConverter  Date converter
     * @param ?callable  $sessionFactory Session factory function
     * @param array      $requestMap     Make request responses map
     *
     * @return MockObject&SierraRest
     */
    protected function createDriver(
        ?Converter $dateConverter = null,
        ?callable $sessionFactory = null,
        array $requestMap = [],
    ): MockObject&SierraRest {
        $dateConverter ??= new \VuFind\Date\Converter();
        $sessionFactory ??= fn ($namespace) => $this->createMock(Container::class);
        $driver = $this->getMockBuilder(SierraRest::class)->setConstructorArgs([$dateConverter, $sessionFactory])
            ->onlyMethods(['makeRequest'])->getMock();
        $driver->expects($this->any())->method('makeRequest')->willReturnMap($requestMap);
        return $driver;
    }
}
