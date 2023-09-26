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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\ILS\Driver\DAIA;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DAIATest extends \VuFindTest\Unit\ILSDriverTestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    protected $testResult = [
        0 =>
            [
                'status' =>    '',
                'availability' => true,
                'duedate' => null,
                'requests_placed' => '',
                'id' => '027586081',
                'item_id' => 'http://uri.gbv.de/document/opac-de-000:epn:711134758',
                'ilslink' => 'http://opac.example-library.edu/loan/REQ?EPN=711134758',
                'number' => 1,
                'barcode' => '1',
                'reserve' => 'N',
                'callnumber' => 'ABC 12',
                'location' => 'Example Library for DAIA Tests',
                'locationid' => 'http://uri.gbv.de/organization/isil/DE-000',
                'locationhref' => 'http://www.example-library.edu',
                'storage' => 'Abteilung III',
                'storageid' => '',
                'storagehref' => '',
                'item_notes' => [],
                'services' => ['loan', 'presentation'],
                'is_holdable' => false,
                'addLink' => false,
                'holdtype' => null,
                'addStorageRetrievalRequestLink' => true,
                'customData' => [],
                'limitation_types' => [],
                'doc_id' => 'http://uri.gbv.de/document/opac-de-000:ppn:027586081',
            ],
        1 =>
            [
                'status' => '',
                'availability' => true,
                'duedate' => null,
                'requests_placed' => '',
                'id' => '027586081',
                'item_id' => 'http://uri.gbv.de/document/opac-de-000:epn:711134766',
                'ilslink' => 'http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081',
                'number' => 2,
                'barcode' => '1',
                'reserve' => 'N',
                'callnumber' => 'DEF 34',
                'location' => 'Example Library for DAIA Tests',
                'locationid' => 'http://uri.gbv.de/organization/isil/DE-000',
                'locationhref' => 'http://www.example-library.edu',
                'storage' => 'Abteilung III',
                'storageid' => '',
                'storagehref' => '',
                'item_notes' => ['mit Zustimmung', 'nur Kopie'],
                'services' => ['loan', 'presentation'],
                'is_holdable' => false,
                'addLink' => false,
                'holdtype' => null,
                'addStorageRetrievalRequestLink' => false,
                'customData' => [],
                'limitation_types' => [],
                'doc_id' => 'http://uri.gbv.de/document/opac-de-000:ppn:027586081',
            ],
        2 =>
            [
                'status' => '',
                'availability' => false,
                'duedate' => '02-09-2115',
                'requests_placed' => '',
                'id' => '027586081',
                'item_id' => 'http://uri.gbv.de/document/opac-de-000:epn:7111347777',
                'ilslink' => 'http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081',
                'number' => 3,
                'barcode' => '1',
                'reserve' => 'N',
                'callnumber' => 'GHI 56',
                'location' => 'Example Library for DAIA Tests',
                'locationid' => 'http://uri.gbv.de/organization/isil/DE-000',
                'locationhref' => 'http://www.example-library.edu',
                'storage' => 'Abteilung III',
                'storageid' => '',
                'storagehref' => '',
                'item_notes' => [],
                'services' => [],
                'is_holdable' => false,
                'addLink' => false,
                'holdtype' => null,
                'addStorageRetrievalRequestLink' => false,
                'customData' => [],
                'limitation_types' => [],
                'doc_id' => 'http://uri.gbv.de/document/opac-de-000:ppn:027586081',
            ],
    ];

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
    public function testJSONgetStatus()
    {
        $conn = $this->createConnector('daia.json');
        $conn->setConfig(
            [
                'DAIA' =>
                    [
                        'baseUrl'            => 'http://daia.gbv.de/',
                        'daiaIdPrefix'       => 'http://uri.gbv.de/document/opac-de-000:ppn:',
                        'daiaResponseFormat' => 'json',
                    ],
            ]
        );
        $conn->init();
        $result = $conn->getStatus('027586081');

        // exact result for using the DAIA.php with testfile daia.json

        $this->assertEquals($result, $this->testResult);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testXMLgetStatus()
    {
        $conn = $this->createConnector('daia.xml');
        $conn->setConfig(
            [
                'DAIA' =>
                    [
                        'baseUrl'            => 'http://daia.gbv.de/',
                        'daiaIdPrefix'       => 'http://uri.gbv.de/document/opac-de-000:ppn:',
                        'daiaResponseFormat' => 'xml',
                    ],
            ]
        );
        $conn->init();
        $result = $conn->getStatus('027586081');

        $this->assertEquals($result, $this->testResult);
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
        $adapter = new TestAdapter();
        if ($fixture) {
            $responseObj = HttpResponse::fromString(
                $this->getFixture("daia/response/$fixture")
            );
            $adapter->setResponse($responseObj);
        }
        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $conn = new DAIA(new \VuFind\Date\Converter());
        $conn->setHttpService($service);
        return $conn;
    }
}
