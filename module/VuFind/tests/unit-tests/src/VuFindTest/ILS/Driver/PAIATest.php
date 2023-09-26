<?php

/**
 * ILS driver test
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\ILS\Driver\PAIA;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PAIATest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    protected $validConfig = [
        'DAIA' =>
            [
                'baseUrl'            => 'http://daia.gbv.de/',
            ],
        'PAIA' =>
            [
                'baseUrl'            => 'http://paia.gbv.de/',
                'grantType'          => 'password',
                'accountBlockNotificationsForMissingScopes' => [
                    'update_patron' => 'ILSMessages::no_update_patron_scope',
                    'read_notifications' => 'ILSMessages::no_read_notifications_scope',
                ],
            ],
    ];

    protected $patron = [
        'id' => '08301001001',
        'firstname' => 'Susan Q.',
        'lastname' => 'Nothing',
        'email' => 'nobody@vufind.org',
        'major' => null,
        'college' => null,
        'name' => ' Susan Q. Nothing',
        'expires' => '9999-12-31',
        'status' => 0,
        'address' => 'No street at all 8, D-21073 Hamburg',
        'type' => [
            0 => 'de-830:user-type:2',
        ],
        'cat_username' => '08301001001',
        'cat_password' => 'NOPASSWORD',
    ];

    protected $patron_bad = [
        'id' => '08301001011',
        'firstname' => 'Invalid',
        'lastname' => 'Nobody',
        'email' => 'nobody_invalid@vufind.org',
        'major' => null,
        'college' => null,
        'name' => ' Nobody Nothing',
        'expires' => '9999-12-31',
        'status' => 9,
        'address' => 'No street at all 8, D-21073 Hamburg',
        'type' => [
            0 => 'de-830:user-type:2',
        ],
        'cat_username' => '08301001011',
        'cat_password' => 'NOPASSWORD',
    ];

    protected $patron_expired = [
        'id' => '08301001111',
        'firstname' => 'Expired',
        'lastname' => 'Nobody',
        'email' => 'nobody_expired@vufind.org',
        'major' => null,
        'college' => null,
        'name' => ' Nobody Nothing',
        'expires' => '2015-12-31',
        'status' => 0,
        'address' => 'No street at all 8, D-21073 Hamburg',
        'type' => [
            0 => 'de-830:user-type:2',
        ],
        'cat_username' => '08301001111',
        'cat_password' => 'NOPASSWORD',
    ];

    protected $feeTestResult = [
        0 =>
            [
                'amount' => 160.0,
                'checkout' => '',
                'fine' => 'Vormerkgebuehr',
                'balance' => 160.0,
                'createdate' => '06-07-2016',
                'duedate' => '',
                'id' => '',
                'title' => 'Open source licensing : software freedom and intellectual property law ; '
                    . '[open source licensees are free to: use open source software for any purpose, make and'
                    . ' distribute copies, create and distribute derivative works, access and use the source code, '
                    . 'com / Rosen, Lawrence (c 2005)',
                'feeid' => null,
                'about' => 'Open source licensing : software freedom and intellectual property law ; '
                    . '[open source licensees are free to: use open source software for any purpose, make and'
                    . ' distribute copies, create and distribute derivative works, access and use the source code, '
                    . 'com / Rosen, Lawrence (c 2005)',
                'item' => 'http://uri.gbv.de/document/opac-de-830:bar:830$28295402',
            ],
        1 =>
            [
                'amount' => 80.0,
                'checkout' => '',
                'fine' => 'Vormerkgebuehr',
                'balance' => 80.0,
                'createdate' => '05-23-2016',
                'duedate' => '',
                'id' => '',
                'title' => 'Test framework in action / Allen, Rob (2009)',
                'feeid' => null,
                'about' => 'Test framework in action / Allen, Rob (2009)',
                'item' => 'http://uri.gbv.de/document/opac-de-830:bar:830$28323471',
            ],
        2 =>
            [
                'amount' => 300.0,
                'checkout' => '',
                'fine' => 'Säumnisgebühr',
                'balance' => 300.0,
                'createdate' => '05-23-2016',
                'duedate' => '',
                'id' => '',
                'title' => 'Unsere historischen Gärten / Lutze, Margot (1986)',
                'feeid' => null,
                'about' => 'Unsere historischen Gärten / Lutze, Margot (1986)',
                'item' => 'http://uri.gbv.de/document/opac-de-830:bar:830$24476416',
            ],
        3 =>
            [
                'amount' => 100.0,
                'checkout' => '',
                'fine' => 'Säumnisgebühr',
                'balance' => 100.0,
                'createdate' => '06-16-2016',
                'duedate' => '',
                'id' => '',
                'title' => 'Triumphe des Backsteins = Triumphs of brick / (1992)',
                'feeid' => null,
                'about' => 'Triumphe des Backsteins = Triumphs of brick / (1992)',
                'item' => 'http://uri.gbv.de/document/opac-de-830:bar:830$33204941',
            ],
        4 =>
            [
                'amount' => 100.0,
                'checkout' => '',
                'fine' => 'Säumnisgebühr',
                'balance' => 100.0,
                'createdate' => '05-23-2016',
                'duedate' => '',
                'id' => '',
                'title' => 'Lehrbuch der Botanik / Strasburger, Eduard (2008)',
                'feeid' => null,
                'about' => 'Lehrbuch der Botanik / Strasburger, Eduard (2008)',
                'item' => 'http://uri.gbv.de/document/opac-de-830:bar:830$26461872',
            ],
    ];

    protected $holdsTestResult = [
        0 =>
            [
                'item_id' => 'http://uri.gbv.de/document/opac-de-830:bar:830$34096983',
                'cancel_details' => '',
                'id' => 'http://uri.gbv.de/document/opac-de-830:ppn:040445623',
                'type' => 'provided',
                'location' => 'Test-Theke',
                'position' => 0,
                'available' => true,
                'title' => 'Praktikum über Entwurf und Manipulation von Datenbanken : SQL/DS (IBM), UDS '
                    . '(Siemens) und MEMODAX / Vossen, Gottfried (1986)',
                'callnumber' => '34:3409-6983',
                'create' => '06-17-2016',
                'expire' => '',
            ],
        1 =>
            [
                'item_id' => 'http://uri.gbv.de/document/opac-de-830:bar:830$28295402',
                'cancel_details' => 'http://uri.gbv.de/document/opac-de-830:bar:830$28295402',
                'id' => 'http://uri.gbv.de/document/opac-de-830:ppn:391260316',
                'type' => 'reserved',
                'location' => 'Ausleihe',
                'position' => 0,
                'available' => false,
                'title' => 'Open source licensing : software freedom and intellectual property law ; '
                    . '[open source licensees are free to: use open source software for any purpose, make and'
                    . ' distribute copies, create and distribute derivative works, access and use the source code, '
                    . 'com / Rosen, Lawrence (c 2005)',
                'callnumber' => '28:2829-5402',
                'create' => '06-15-2016',
                'duedate' => '06-15-2016',
            ],
    ];

    protected $requestsTestResult = [
        0 =>
            [
                'item_id' => 'http://uri.gbv.de/document/opac-de-830:bar:830$24260127',
                'cancel_details' => '',
                'id' => 'http://uri.gbv.de/document/opac-de-830:ppn:020966334',
                'type' => 'ordered',
                'location' => 'Ausleihe',
                'position' => 0,
                'available' => false,
                'title' => 'Gold / Kettell, Brian (1982)',
                'callnumber' => '24:2426-0127',
                'create' => '04-25-2016',
            ],
    ];

    protected $transactionsTestResult = [
        0 =>
            [
                'item_id' => 'http://uri.gbv.de/document/opac-de-830:bar:830$28342436',
                'id' => 'http://uri.gbv.de/document/opac-de-830:ppn:58891861X',
                'title' => 'Theoretische Informatik : mit 22 Tabellen und 78 Aufgaben / Hoffmann, Dirk W. (2009)',
                'callnumber' => '28:2834-2436',
                'renewable' => false,
                'renew_details' => '',
                'request' => 0,
                'renew' => 12,
                'reminder' => 1,
                'startTime' => '11-15-2013',
                'dueTime' => '06-15-2016',
                'duedate' => '',
                'message' => '',
                'borrowingLocation' => 'Ausleihe',
                'type' => 'held',
                'location' => 'Ausleihe',
                'position' => 0,
                'available' => false,
                'create' => '11-15-2013',
                'cancel_details' => '',
            ],
        1 =>
            [
                'renewable' => false,
                'item_id' => 'http://uri.gbv.de/document/opac-de-830:bar:830$22278001',
                'renew_details' => '',
                'id' => 'http://uri.gbv.de/document/opac-de-830:ppn:659228084',
                'title' => 'Linked Open Library Data : bibliographische Daten und ihre Zugänglichkeit im Web der'
                    . ' Daten ; Innovationspreis 2011 / Fürste, Fabian M. (2011)',
                'request' => 0,
                'renew' => 9,
                'reminder' => 0,
                'startTime' => '12-22-2011',
                'dueTime' => '07-14-2016',
                'duedate' => '',
                'message' => '',
                'borrowingLocation' => 'Ausleihe',
                'callnumber' => '22:2227-8001',
                'type' => 'held',
                'location' => 'Ausleihe',
                'position' => 0,
                'available' => false,
                'create' => '12-22-2011',
                'cancel_details' => '',
            ],
    ];

    protected $renewTestResult = [
        'blocks' => false,
        'details' => [
            'http://uri.gbv.de/document/opac-de-830:bar:830$22061137' => [
                'success' => true,
                'new_date' => '07-18-2016',
                'item_id' => 0,
                'sysMessage' => 'Successfully renewed',
            ],
        ],
    ];

    protected $storageRetrievalTestResult = [
        'success' => true,
        'sysMessage' => 'Successfully requested',
    ];

    protected $pwchangeTestResult = [
        'success' => true,
        'status' => 'Successfully changed',
    ];

    protected $profileTestResult = [
        'firstname' => 'Susan Q.',
        'lastname' => 'Nothing',
        'address1' => 'No street at all 8, D-21073 Hamburg',
        'address2' => null,
        'city' => null,
        'country' => null,
        'zip' => null,
        'phone' => null,
        'group' => 'de-830:user-type:2',
        'mobile_phone' => null,
        'expires' => '12-31-9999',
        'statuscode' => 0,
        'canWrite' => true,
    ];

    /*******************
     * Test cases
     ***************/
    /*
     ok changePassword
     ok checkRequestIsValid
     ok checkStorageRetrievalRequestIsValid
     ok getMyProfile
     ok getMyFines
     ok getMyHolds
     ok getMyTransactions
     ok getRenewDetails
     ok getMyStorageRetrievalRequests
     ok placeHold
     ok renewMyItems
     ok placeStorageRetrievalRequest
     */

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = $this->createConnector();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testChangePassword()
    {
        $changePasswordTestdata = [
            'patron' => [
                'cat_username' => '08301001001',
             ],
             'oldPassword' => 'oldsecret',
             'newPassword' => 'newsecret',
        ];

        $conn = $this->createMockConnector('changePassword.json');
        $result = $conn->changePassword($changePasswordTestdata);
        $this->assertEquals($this->pwchangeTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testFees()
    {
        $conn = $this->createMockConnector('fees.json');
        $result = $conn->getMyFines($this->patron);

        $this->assertEquals($this->feeTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testHolds()
    {
        $conn = $this->createMockConnector('items.json');
        $result = $conn->getMyHolds($this->patron);

        $this->assertEquals($this->holdsTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRequests()
    {
        $conn = $this->createMockConnector('items.json');
        $result = $conn->getMyStorageRetrievalRequests($this->patron);

        $this->assertEquals($this->requestsTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTransactions()
    {
        $conn = $this->createMockConnector('items.json');
        $result = $conn->getMyTransactions($this->patron);

        $this->assertEquals($this->transactionsTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testProfile()
    {
        $conn = $this->createMockConnector('patron.json');
        $result = $conn->getMyProfile($this->patron);

        $this->assertEquals($this->profileTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testValidRequest()
    {
        $conn = $this->createMockConnector('patron.json');

        $result = $conn->checkRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron
        );
        $resultStorageRetrieval = $conn->checkStorageRetrievalRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron
        );
        $result_bad = $conn->checkRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron_bad
        );
        $resultStorage_bad = $conn->checkStorageRetrievalRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron_bad
        );
        $result_expired = $conn->checkRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron_expired
        );
        $resultStorage_expired = $conn->checkStorageRetrievalRequestIsValid(
            'http://paia.gbv.de/',
            [],
            $this->patron_expired
        );

        $this->assertEquals(true, $result);
        $this->assertEquals(true, $resultStorageRetrieval);
        $this->assertEquals(false, $result_bad);
        $this->assertEquals(false, $resultStorage_bad);
        $this->assertEquals(false, $result_expired);
        $this->assertEquals(false, $resultStorage_expired);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRenewDetails()
    {
        $conn = $this->createMockConnector('');
        $result = $conn->getRenewDetails($this->transactionsTestResult[1]);

        $this->assertEquals('', $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testPlaceHold()
    {
        $sr_request = [
            'item_id'     => 'http://uri.gbv.de/document/opac-de-830:bar:830$24014292',
            'patron' => [
                'cat_username' => '08301001001',
            ],
        ];

        $conn = $this->createMockConnector('storageretrieval.json');
        $result = $conn->placeHold($sr_request);
        $this->assertEquals($this->storageRetrievalTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testPlaceStorageRetrievalRequest()
    {
        $sr_request = [
            'item_id'     => 'http://uri.gbv.de/document/opac-de-830:bar:830$24014292',
            'patron' => [
                'cat_username' => '08301001001',
            ],
        ];

        $conn = $this->createMockConnector('storageretrieval.json');
        $result = $conn->placeStorageRetrievalRequest($sr_request);
        $this->assertEquals($this->storageRetrievalTestResult, $result);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRenew()
    {
        $renew_request = [
            'details' => [
                'item'     => 'http://uri.gbv.de/document/opac-de-830:bar:830$22061137',
            ],
            'patron' => [
                'cat_username' => '08301001001',
            ],
        ];

        $conn = $this->createMockConnector('renew_ok.json');
        $result = $conn->renewMyItems($renew_request);

        $this->assertEquals($this->renewTestResult, $result);

        /* TODO: make me work
            $conn_fail = $this->createConnector('renew_error.json');
            $connfail->setConfig($this->validConfig);
            $conn_fail->init();
            $result_fail = $conn_fail->renewMyItems($renew_request);

            $this->assertEquals($this->failedRenewTestResult, $result_fail);
        */
    }

    /**
     * Test getAccountBlocks
     *
     * @return void
     */
    public function testGetAccountBlocks()
    {
        $patron = [];
        $paia = $this->createMockConnector();
        $blocks = $paia->getAccountBlocks($patron);
        $this->assertEquals([
            'ILSMessages::no_update_patron_scope',
            'ILSMessages::no_read_notifications_scope',
        ], $blocks);
    }

    /**
     * Create HTTP service for testing.
     *
     * @param string $fixture Fixture file
     *
     * @return \VuFindHttp\HttpService
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function getHttpService($fixture = null)
    {
        $adapter = new TestAdapter();
        if ($fixture) {
            $responseObj = HttpResponse::fromString(
                $this->getFixture("paia/response/$fixture")
            );
            $adapter->setResponse($responseObj);
        }
        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        return $service;
    }

    /**
     * Create connector with fixture file.
     *
     * @param string $fixture Fixture file
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector($fixture = null)
    {
        $service = $this->getHttpService($fixture);
        $conn = new PAIA(
            new \VuFind\Date\Converter(),
            new \Laminas\Session\SessionManager()
        );
        $conn->setHttpService($service);
        return $conn;
    }

    /**
     * Create connector with fixture file.
     *
     * @param string $fixture Fixture file
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createMockConnector($fixture = null)
    {
        $service = $this->getHttpService($fixture);
        $dateConverter = new \VuFind\Date\Converter();
        $sessionManager = new \Laminas\Session\SessionManager();
        $conn = $this->getMockBuilder(\VuFind\ILS\Driver\PAIA::class)
            ->setConstructorArgs([$dateConverter, $sessionManager])
            ->onlyMethods(['getScope'])
            ->getMock();
        $conn->expects($this->any())->method('getScope')
            ->will(
                $this->returnValue(
                    [
                    'write_items',
                    'change_password',
                    'read_fees',
                    'read_items',
                    'read_patron',
                    ]
                )
            );
        $conn->setHttpService($service);
        $conn->setConfig($this->validConfig);
        $conn->init();
        return $conn;
    }
}
