<?php

/**
 * Aleph ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Response as HttpResponse;
use RuntimeException;
use VuFind\ILS\Driver\Aleph;

/**
 * Aleph ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AlephTest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new Aleph(new \VuFind\Date\Converter());
    }

    /**
     * Test the getMyFines() method
     *
     * @return void
     */
    public function testGetMyFines(): void
    {
        $this->mockResponse('cash.xml');
        $fines = $this->driver->getMyFines(
            [
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'id' => 'patron_id',
            ]
        );
        $expected = [
            [
                'title' => '',
                'barcode' => '2631080653',
                'amount' => 10300.0,
                'transactiondate' => '06-08-2015',
                'transactiontype' => 'K dobru',
                'balance' => 10300.0,
                'checkout' => '08-06-2015',
                'id' => null,
                'printLink' => 'test',
                'fine' => 'Ztrata knihy - nahrada',
            ],
            [
                'title' => 'Deštník Studijní a vědecké knihovny Plzeňského kraje : výpůjčka na 14 dní = The Umbrella',
                'barcode' => '263MD00005',
                'amount' => 10300.0,
                'transactiondate' => '21-11-2017',
                'transactiontype' => 'K dobru',
                'balance' => 10300.0,
                'checkout' => '11-21-2017',
                'id' => null,
                'printLink' => 'test',
                'fine' => 'Ztráta deštníku  - náhrada',
            ],
            [
                'title' => 'K přijímačkám s nadhledem : příprava na jednotné přijímací zkoušky z českého jazyka 9.',
                'barcode' => '2631080653',
                'amount' => -6000.0,
                'transactiondate' => '15-02-2018',
                'transactiontype' => 'K tíži',
                'balance' => -6000.0,
                'checkout' => '02-15-2018',
                'id' => null,
                'printLink' => 'test',
                'fine' => '2. upomínka',
            ],
            [
                'title' => 'K přijímačkám s nadhledem : příprava na jednotné přijímací zkoušky z českého jazyka 9.',
                'barcode' => '2631080653',
                'amount' => -12000.0,
                'transactiondate' => '19-03-2018',
                'transactiontype' => 'K tíži',
                'balance' => -12000.0,
                'checkout' => '03-19-2018',
                'id' => null,
                'printLink' => 'test',
                'fine' => '3. upomínka / předžalobní výzva',
            ],
            [
                'title' => 'K přijímačkám s nadhledem : příprava na jednotné přijímací zkoušky z českého jazyka 9.',
                'barcode' => '2631080653',
                'amount' => -19000.0,
                'transactiondate' => '09-04-2018',
                'transactiontype' => 'K tíži',
                'balance' => -19000.0,
                'checkout' => '04-09-2018',
                'id' => null,
                'printLink' => 'test',
                'fine' => 'Pozdní vrácení',
            ],
            [
                'title' => '',
                'barcode' => '',
                'amount' => -12000.0,
                'transactiondate' => '16-04-2018',
                'transactiontype' => 'K tíži',
                'balance' => -12000.0,
                'checkout' => '04-16-2018',
                'id' => null,
                'printLink' => 'test',
                'fine' => 'Prodloužení čt. průkazu',
             ],
        ];
        $this->assertEquals($expected, $fines);
    }

    /**
     * Data provider for testing getMyProfile
     *
     * @return Generator
     */
    public static function getTestGetMyProfileData(): \Generator
    {
        yield 'Test fixture 2' => [
            'fixture' => 'profile2.xml',
            'xserver_enabled' => false,
            'expected' => [
                'firstname' => 'First',
                'lastname' => 'Tester',
                'birthdate' => '',
                'address1' => 'Teststreet',
                'address2' => '',
                'city' => 'Test City',
                'country' => null,
                'zip' => '',
                'phone' => '',
                'mobile_phone' => null,
                'expiration_date' => '19990101',
                'group' => 'Test',
                'email' => 'test@email.t',
                'home_library' => null,
                'fullname' => 'Tester,First',
                'cat_username' => '1111',
                'id' => '1111',
            ],
        ];

        yield 'Test fixture 1' => [
            'fixture' => 'profile1.xml',
            'xserver_enabled' => true,
            'expected' => [
                'firstname' => 'Arcanis',
                'lastname' => 'The',
                'birthdate' => '',
                'address1' => 'Teststreet',
                'address2' => '',
                'city' => null,
                'country' => null,
                'zip' => '',
                'phone' => '0123456789',
                'mobile_phone' => null,
                'expiration_date' => null,
                'group' => 'Omnipotent',
                'barcode' => 'barcode',
                'expire' => '20220120',
                'credit_sum' => '9999',
                'credit_sign' => 'E',
                'cat_username' => '1111',
                'email' => null,
                'home_library' => null,
                'id' => '1',
                'credit' => '20220120',
            ],
        ];
    }

    /**
     * Test getMyProfile
     *
     * @param string $fixture         Fixture file name located in aleph fixtures folder
     * @param bool   $xserver_enabled Use xserver
     * @param array  $expected        Expected results for the test
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestGetMyProfileData')]
    public function testGetMyProfile(string $fixture, bool $xserver_enabled, array $expected): void
    {
        $mockRequest = $xserver_enabled ? 'doXRequest' : 'doRestDLFRequest';
        $driver = $this->getMockBuilder(Aleph::class)->disableOriginalConstructor()
            ->onlyMethods([$mockRequest, 'parseDate'])->getMock();
        $driver->expects($this->any())->method('parseDate')->willReturnCallback(fn ($date) => $date);
        $fixture = $this->getFixture('aleph/' . $fixture);
        $driver->expects($this->any())->method($mockRequest)->willReturn(simplexml_load_string($fixture));
        $config = [
            'Catalog' => [
                'host' => 'test.test',
                'bib' => '123',
                'useradm' => 'ad',
                'admlib' => 'ad',
                'dlfport' => 1111,
                'available_statuses' => '1',
            ],
            'sublibadm' => 'sadm',
        ];
        if ($xserver_enabled) {
            $config['Catalog']['wwwuser'] = 'usr';
            $config['Catalog']['wwwpasswd'] = 'passwd';
        }
        $driver->setConfig($config);
        $driver->init();
        $result = $driver->getMyProfile(['id' => '1111']);
        $this->assertEquals($expected, $result);
    }

    /**
     * Mock fixture as HTTP client response
     *
     * @param string|array|null $fixture Fixture file
     *
     * @return void
     * @throws InvalidArgumentException Fixture file could not be loaded as HTTP response
     * @throws RuntimeException         Fixture file does not exist
     */
    protected function mockResponse($fixture = null)
    {
        $adapter = new TestAdapter();
        if (!empty($fixture)) {
            $fixture = (array)$fixture;
            $responseObj = $this->loadResponse($fixture[0]);
            $adapter->setResponse($responseObj);
            array_shift($fixture);
            foreach ($fixture as $f) {
                $responseObj = $this->loadResponse($f);
                $adapter->addResponse($responseObj);
            }
        }

        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $this->driver->setHttpService($service);
    }

    /**
     * Load response from file
     *
     * @param string $filename File name of raw HTTP response
     *
     * @return HttpResponse Response object
     * @throws InvalidArgumentException Fixture file could not be loaded as HTTP response
     * @throws RuntimeException         Fixture file does not exist
     */
    protected function loadResponse($filename)
    {
        return HttpResponse::fromString(
            $this->getFixture("aleph/$filename")
        );
    }
}
