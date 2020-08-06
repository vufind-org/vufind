<?php
/**
 * ILS driver test
 *
 * PHP version 7
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\ILS\Driver;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\ILS\Driver\XCNCIP2;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class XCNCIP2Test extends \VuFindTest\Unit\ILSDriverTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->driver = new XCNCIP2(new \VuFind\Date\Converter());
    }

    protected $transactionsTests = [
        [
            'file' => [
                'lookupUserResponse.xml',
                'LookupItem.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => false,
                ],
                [
                    'id' => 'KN3183000000046386',
                    'item_agency_id' => 'Agency from lookup item',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
        [
            'file' => [
                'LookupUserResponseWithoutNamespacePrefix.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => true,
                ],
                [
                    'id' => 'MZK01000000456-MZK50000000456000440',
                    'item_agency_id' => 'My Agency',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
        [
            'file' => [
                'LookupUserResponseWithoutNamespaceDefinition.xml',
            ],
            'result' => [
                [
                    'id' => 'MZK01000847602-MZK50000847602000090',
                    'item_agency_id' => 'My Agency',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-19-2014',
                    'title' => 'Jahrbücher der Deutschen Malakozoologischen Gesellschaft ...',
                    'item_id' => '104',
                    'renewable' => true,
                ],
                [
                    'id' => 'MZK01000000456-MZK50000000456000440',
                    'item_agency_id' => 'My Agency',
                    'patron_agency_id' => 'Test agency',
                    'duedate' => '11-26-2014',
                    'title' => 'Anna Nahowská a císař František Josef : zápisky / Friedrich Saathen ; z něm. přel. Ivana Víz',
                    'item_id' => '105',
                    'renewable' => true,
                ],
            ],
        ],
    ];

    protected $finesTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '8071750247',
                    'duedate' => '',
                    'amount' => 25,
                    'balance' => 25,
                    'checkout' => '',
                    'fine' => 'Service Charge',
                    'createdate' => '11-14-2014',
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '',
                    'duedate' => '',
                    'amount' => 25,
                    'balance' => 25,
                    'checkout' => '',
                    'fine' => 'Service Charge',
                    'createdate' => '11-14-2014',
                ],
            ],
        ],
    ];

    protected $loginTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                'id' => '700',
                'patron_agency_id' => 'MZK',
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'email' => 'test@mzk.cz',
                'major' => null,
                'college' => null,
                'firstname' => 'John',
                'lastname' => 'Smith',
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                'id' => '700',
                'patron_agency_id' => 'MZK',
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'email' => 'test@mzk.cz',
                'major' => null,
                'college' => null,
                'firstname' => 'John',
                'lastname' => 'Smith',
            ],
        ],
    ];

    protected $holdsTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '111',
                    'title' => 'Ahoj, Blanko! : dívčí román / Eva Bernardinová',
                    'item_id' => 'MZK01000353880-MZK50000353880000040',
                    'create' => '',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                ],
                [
                    'id' => '112',
                    'title' => 'Aktiv revizních techniků elektrických zařízení',
                    'item_id' => 'MZK01000065021-MZK50000065021000010',
                    'create' => '',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '111',
                    'title' => 'Ahoj, Blanko! : dívčí román / Eva Bernardinová',
                    'item_id' => 'MZK01000353880-MZK50000353880000040',
                    'create' => '',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                ],
                [
                    'id' => '112',
                    'title' => 'Aktiv revizních techniků elektrických zařízení',
                    'item_id' => 'MZK01000065021-MZK50000065021000010',
                    'create' => '',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                ],
            ],
        ],
    ];

    protected $profileTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                'firstname' => 'John',
                'lastname' => 'Smith',
                'address1' => 'Trvalá ulice 123, Big City, 12345',
                'address2' => '',
                'zip' => '',
                'phone' => '',
                'group' => '',
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                'firstname' => 'John',
                'lastname' => 'Smith',
                'address1' => 'Trvalá ulice 123, Big City, 12345',
                'address2' => '',
                'zip' => '',
                'phone' => '',
                'group' => '',
            ],
        ],
    ];

    protected $storageRetrievalTests = [
        [
            'file' => 'lookupUserResponse.xml',
            'result' => [
                [
                    'id' => '155',
                    'title' => 'Listen and play : with magicians! : 3. ročník / Věra Štiková ; [ilustrace Andrea Schindlerová]',
                    'create' => '11-09-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'processed' => false,
                ],
            ],
        ],
        [
            'file' => 'LookupUserResponseWithoutNamespacePrefix.xml',
            'result' => [
                [
                    'id' => '155',
                    'title' => 'Listen and play : with magicians! : 3. ročník / Věra Štiková ; [ilustrace Andrea Schindlerová]',
                    'create' => '11-09-2014',
                    'expire' => null,
                    'position' => null,
                    'requestId' => null,
                    'location' => 'Loan Department - Ground floor',
                    'item_agency_id' => null,
                    'canceled' => false,
                    'processed' => false,
                ],
            ],
        ],
    ];

    protected $statusesTests = [
        [
            'file' => 'lookupItemSet.xml',
            'result' => [
                'MZK01000000421' => [
                    [
                        'status' => 'Available on shelf',
                        'location' => null,
                        'callnumber' => '621.3 ANG',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000421',
                    ],
                ],
                'MZK01000062021' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => null,
                        'callnumber' => 'PK-0083.568',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000062021',
                    ],
                ],
                'MZK01000000425' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => '2-0997.767,2',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                    [
                        'status' => 'Circulation Status Undefined',
                        'location' => 'Some holding location',
                        'callnumber' => null,
                        'availability' => false,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                        'use_unknown_message' => true,
                    ],
                ],
            ],
        ],
        [
            'file' => 'lookupItemSetWithoutNamespacePrefix.xml',
            'result' => [
                'MZK01000000421' => [
                    [
                        'status' => 'Available on shelf',
                        'location' => null,
                        'callnumber' => '621.3 ANG',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000421',
                    ],
                ],
                'MZK01000062021' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => null,
                        'callnumber' => 'PK-0083.568',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000062021',
                    ],
                ],
                'MZK01000000425' => [
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => '2-0997.767,2',
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                    [
                        'status' => 'Available On Shelf',
                        'location' => 'Some holding location',
                        'callnumber' => null,
                        'availability' => true,
                        'reserve' => 'N',
                        'id' => 'MZK01000000425',
                    ],
                ],
            ],
        ],
    ];

    protected $placeHoldTests = [
        [
            'file' => 'RequestItemResponseAcceptedWithItemId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseAcceptedWithRequestId.xml',
            'result' => [
                'success' => true,
                'sysMessage' => 'Request Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDenied.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedWithIdentifiers.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedNotFullProblemElement.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],
        [
            'file' => 'RequestItemResponseDeniedEmpty.xml',
            'result' => [
                'success' => false,
                'sysMessage' => 'Request Not Successful.'
            ],
        ],

    ];

    /**
     * Test getMyTransactions
     *
     * @return void
     */
    public function testGetMyTransactions()
    {
        $this->configureDriver();
        foreach ($this->transactionsTests as $test) {
            $this->mockResponse($test['file']);
            $transactions = $this->driver->getMyTransactions([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patron_agency_id' => 'Test agency',
            ]);
            $this->assertEquals(
                $test['result'], $transactions, 'Fixture file: ' . implode(', ', (array)$test['file'])
            );
        }
    }

    /**
     * Test getMyFines
     *
     * @return void
     */
    public function testGetMyFines()
    {
        $this->configureDriver();
        foreach ($this->finesTests as $test) {
            $this->mockResponse($test['file']);
            $fines = $this->driver->getMyFines(
                [
                    'cat_username' => 'my_login', 'cat_password' => 'my_password',
                    'patron_agency_id' => 'Test agency',
                ]
            );
            $this->assertEquals($test['result'], $fines, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Test patronLogin
     *
     * @return void
     */
    public function testPatronLogin()
    {
        $this->configureDriver();
        foreach ($this->loginTests as $test) {
            $this->mockResponse($test['file']);
            $patron = $this->driver->patronLogin('my_login', 'my_password');
            $this->assertEquals($test['result'], $patron);
        }
    }

    /**
     * Test getMyHolds
     *
     * @return void
     */
    public function testGetMyHolds()
    {
        $this->configureDriver();
        foreach ($this->holdsTests as $test) {
            $this->mockResponse($test['file']);
            $holds = $this->driver->getMyHolds([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patron_agency_id' => 'Test agency',
             ]);
            $this->assertEquals($test['result'], $holds);
        }
    }

    /**
     * Test getMyProfile
     *
     * @return void
     */
    public function testGetMyProfile()
    {
        $this->configureDriver();
        foreach ($this->profileTests as $test) {
            $this->mockResponse($test['file']);
            $profile = $this->driver->getMyProfile(
                [
                    'cat_username' => 'my_login',
                    'cat_password' => 'my_password',
                    'patron_agency_id' => 'Test agency',
                ]
            );
            $this->assertEquals($test['result'], $profile);
        }
    }

    /**
     * Test getMyStorageRetrievalRequests
     *
     * @return void
     */
    public function testGetMyStorageRetrievalRequests()
    {
        $this->configureDriver();
        foreach ($this->storageRetrievalTests as $test) {
            $this->mockResponse($test['file']);
            $storageRetrievals = $this->driver->getMyStorageRetrievalRequests([
                'cat_username' => 'my_login',
                'cat_password' => 'my_password',
                'patron_agency_id' => 'Test agency',
            ]);
            $this->assertEquals($test['result'], $storageRetrievals);
        }
    }

    public function testGetStatuses()
    {
        $this->configureDriver();
        foreach ($this->statusesTests as $test) {
            $this->mockResponse($test['file']);
            $status = $this->driver->getStatuses(['Some Id']);
            $this->assertEquals($test['result'], $status);
        }
    }

    public function testGetPickupLocations()
    {
        // Test reading pickup locations from file
        $this->configureDriver();
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([
            [
                'locationID' => 'My University|1',
                'locationDisplay' => 'Main Circulation Desk',
            ],
            [
                'locationID' => 'My University|2',
                'locationDisplay' => 'Stacks',
            ]
        ], $locations);

        // Test reading pickup locations from NCIP responder
        $this->configureDriver([
                'Catalog' => [
                    'url' => 'https://test.ncip.example',
                    'consortium' => false,
                    'agency' => ['Test agency'],
                    'pickupLocationsFromNCIP' => true,
                ],
                'NCIP' => [],
            ]);
        $this->mockResponse('LookupAgencyResponse.xml');
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([
            [
                'locationID' => 'My library|1',
                'locationDisplay' => 'Main library',
            ],
            [
                'locationID' => 'My library|2',
                'locationDisplay' => 'Stacks',
            ]
        ], $locations);

        // Test reading pickup locations from NCIP, but response is without locations
        $this->configureDriver([
            'Catalog' => [
                'url' => 'https://test.ncip.example',
                'consortium' => false,
                'agency' => ['Test agency'],
                'pickupLocationsFromNCIP' => true,
            ],
            'NCIP' => [],
        ]);
        $this->mockResponse('LookupAgencyResponseWithoutLocations.xml');
        $locations = $this->driver->getPickUpLocations([]);
        $this->assertEquals([], $locations);
    }

    /**
     * Test placeHold
     *
     * @return void
     */
    public function testPlaceHold()
    {
        $this->configureDriver();
        foreach ($this->placeHoldTests as $test) {
            $this->mockResponse($test['file']);
            $hold = $this->driver->placeHold(
                [
                    'patron' => [
                        'cat_username' => 'my_login',
                        'cat_password' => 'my_password',
                        'patron_agency_id' => 'Test agency',
                    ],
                    'bib_id' => '1',
                    'item_id' => '1',
                    'pickUpLocation' => 'My University|1',
                    'holdtype' => 'title',
                    'requiredBy' => '2020-12-30',
                    'item_agency_id' => 'My University',
                ]
            );
            $this->assertEquals($test['result'], $hold, 'Fixture file: ' . implode(', ', (array)$test['file']));
        }
    }

    /**
     * Mock fixture as HTTP client response
     *
     * @param string|array|null $fixture Fixture file
     **
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function mockResponse($fixture = null)
    {
        $adapter = new TestAdapter();
        if (is_string($fixture)) {
            $responseObj = $this->loadResponse($fixture);
            $adapter->setResponse($responseObj);
        }
        if (is_array($fixture)) {
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

    protected function loadResponse($filename)
    {
        $file = realpath(
            __DIR__ .
            '/../../../../../../tests/fixtures/xcncip2/response/' . $filename
        );
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(
                sprintf('Unable to load fixture file: %s ', $file)
            );
        }
        $response = file_get_contents($file);
        return HttpResponse::fromString($response);
    }

    /**
     * Configure driver for test case
     *
     * @param array|null $config
     *
     * @return void
     */
    protected function configureDriver($config = null)
    {
        $this->driver = new XCNCIP2(new \VuFind\Date\Converter());
        $this->driver->setConfig($config ?? [
            'Catalog' => [
                'url' => 'https://test.ncip.example',
                'consortium' => false,
                'agency' => ['Test agency'],
                'pickupLocationsFile' => 'XCNCIP2_locations.txt',
            ],
            'NCIP' => [],
        ]);
        $this->driver->init();
    }
}
