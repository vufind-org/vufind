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
                        'daiaIdPrefix'       => "opac-de-000:ppn:",
                        'daiaResponseFormat' => 'json',
                    ]
            ]
        );
        $conn->init();
        $result = $conn->getStatus('0001880463');

        
        // exact result for using the DAIA.php with testfile daia.json
        $testResult = [
            0 =>
                [
                    'status' =>    null,
                    'availability' => true,
                    'duedate' => null,
                    'id' => "0001880463",
                    'item_id' => "0001880463",
                    'number' => 1,
                    'barcode' => "1",
                    'reserve' => "N",
                    'callnumber' => "ABC 12",
                    'location' => "Abteilung III",
                ],
                1 =>
                [
                    'status' => null,
                    'availability' => true,
                    'duedate' => null,
                    'id' => "0001880463",
                    'item_id' => "0001880463",
                    'number' => 2,
                    'barcode' => "1",
                    'reserve' => "N",
                    'callnumber' => "DEF 34",
                    'location' => "Abteilung III",
                ],
                2 =>
                [
                    'status' => "dummy text",
                    'availability' => false,
                    'duedate' => "2115-02-09",
                    'id' => "0001880463",
                    'item_id' => "0001880463",
                    'number' => 3,
                    'barcode' => "1",
                    'reserve' => "N",
                    'callnumber' => "GHI 56",
                    'location' => "Abteilung III",
                ],
        ];

        $this->assertEquals($result, $testResult);
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
                        'daiaIdPrefix'       => "opac-de-000:ppn:",
                        'daiaResponseFormat' => 'xml',
                    ]
            ]
        );
        $conn->init();
        $result = $conn->getStatus('0001880463');
        
        // exact result for using the DAIA.php with testfile daia.xml
        $testResult = [
            0 => [
                    'callnumber' => "ABC 12",
                    'availability' => "1",
                    'number' => 1,
                    'reserve' => "No",
                    'duedate' => "",
                    'queue' => "",
                    'delay' => "unknown",
                    'barcode' => 1,
                    'status' => "",
                    'id' => "0001880463",
                    'item_id' =>
                     "http://uri.gbv.de/document/opac-de-000:epn:711134758",
                    'recallhref' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                    'location' => "Abteilung III",
                    'location.id' =>
                     "http://uri.gbv.de/organization/isil/DE-000",
                    'location.href' => "http://www.example-library.edu",
                    'label' => "ABC 12",
                    'notes' => [],
                    'presentation.availability' => "1",
                    'presentation_availability' => "1",
                    'presentation.delay' => "unknown",
                    'loan.availability' => "1",
                    'loan_availability' => "1",
                    'loan.delay' => "unknown",
                    'interloan.availability' => "1",
                    'interloan.delay' => "unknown",
                    'ilslink' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                ],
            1 => [
                    'callnumber' => "DEF 34",
                    'availability' => "1",
                    'number' => 2,
                    'reserve' => "No",
                    'duedate' => "",
                    'queue' => "",
                    'delay' => "",
                    'barcode' => 1,
                    'status' => "",
                    'id' => "0001880463",
                    'item_id' =>
                     "http://uri.gbv.de/document/opac-de-000:epn:711134766",
                    'recallhref' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                    'location' => "Abteilung III",
                    'location.id' =>
                     "http://uri.gbv.de/organization/isil/DE-000",
                    'location.href' => "http://www.example-library.edu",
                    'label' => "DEF 34",
                    'notes' => [],
                    'presentation.availability' => "1",
                    'presentation_availability' => "1",
                    'loan.availability' => "1",
                    'loan_availability' => "1",
                    'interloan.availability' => "1",
                    'ilslink' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                ],
            2 => [
                    'callnumber' => "GHI 56",
                    'availability' => "0",
                    'number' => 3,
                    'reserve' => "No",
                    'duedate' => "2115-02-09",
                    'queue' => "",
                    'delay' => "",
                    'barcode' => 1,
                    'status' => "",
                    'id' => "0001880463",
                    'item_id' =>
                     "http://uri.gbv.de/document/opac-de-000:epn:7111347777",
                    'recallhref' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                    'location' => "Abteilung III",
                    'location.id' =>
                     "http://uri.gbv.de/organization/isil/DE-000",
                    'location.href' => "http://www.example-library.edu",
                    'label' => "GHI 56",
                    'notes' => [],
                    'presentation.availability' => "0",
                    'presentation_availability' => "0",
                    'presentation.duedate' => "2115-02-09",
                    'loan.availability' => "0",
                    'loan_availability' => "0",
                    'loan.duedate' => "2115-02-09",
                    'interloan.availability' => "0",
                    'interloan.duedate' => "2115-02-09",
                    'ilslink' =>
                     "http://opac.example-library.edu/DB=1/PPNSET?PPN=027586081",
                ],
        ];

        $this->assertEquals($result, $testResult);
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
            $responseObj = new HttpResponse();
            $responseObj->setContent($response);
            $adapter->setResponse($responseObj);
        }
        $service = new \VuFindHttp\HttpService();
        $service->setDefaultAdapter($adapter);
        $conn = new DAIA();
        $conn->setHttpService($service);
        return $conn;
    }
}