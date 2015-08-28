<?php
/**
 * ILS driver test
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\DAIA;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Response as HttpResponse;

use InvalidArgumentException;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DAIATest extends \VuFindTest\Unit\ILSDriverTestCase
{
    protected $testResult = [
        0 =>
            [
                'status' =>    '',
                'availability' => true,
                'duedate' => null,
                'requests_placed' => '',
                'id' => "027586081",
                'item_id' => "http://uri.gbv.de/document/opac-de-000:epn:711134758",
                'ilslink' => "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                'number' => 1,
                'barcode' => "1",
                'reserve' => "N",
                'callnumber' => "ABC 12",
                'location' => "Abteilung III",
                'locationhref' => false
            ],
        1 =>
            [
                'status' => 'nur Kopie',
                'availability' => true,
                'duedate' => null,
                'requests_placed' => '',
                'id' => "027586081",
                'item_id' => "http://uri.gbv.de/document/opac-de-000:epn:711134766",
                'ilslink' => "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                'number' => 2,
                'barcode' => "1",
                'reserve' => "N",
                'callnumber' => "DEF 34",
                'location' => "Abteilung III",
                'locationhref' => false
            ],
        2 =>
            [
                'status' => '',
                'availability' => false,
                'duedate' => "02-09-2115",
                'requests_placed' => '',
                'id' => "027586081",
                'item_id' => "http://uri.gbv.de/document/opac-de-000:epn:7111347777",
                'ilslink' => "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                'number' => 3,
                'barcode' => "1",
                'reserve' => "N",
                'callnumber' => "GHI 56",
                'location' => "Abteilung III",
                'locationhref' => false
            ],
    ];

    /**
     * Constructor
     */
    public function __construct()
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
                    ]
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
                    ]
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
            $file = realpath(
                __DIR__ .
                '/../../../../../../tests/fixtures/daia/response/' . $fixture
            );
            if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
                throw new InvalidArgumentException(
                    sprintf('Unable to load fixture file: %s ', $file)
                );
            }
            $response = file_get_contents($file);
            $responseObj = HttpResponse::fromString($response);
            $adapter->setResponse($responseObj);
        }
        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $conn = new DAIA(new \VuFind\Date\Converter());
        $conn->setHttpService($service);
        return $conn;
    }
}
