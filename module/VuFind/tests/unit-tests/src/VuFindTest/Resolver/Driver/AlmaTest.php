<?php
/**
 * Alma resolver driver test
 *
 * PHP version 7
 *
 * Copyright (C) Leipzig University Library 2015.
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Resolver\Driver;

use InvalidArgumentException;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;

use VuFind\Resolver\Driver\Alma;

/**
 * Alma resolver driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AlmaTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test-Config
     *
     * @var array
     */
    protected $openUrlConfig = [
        'OpenURL' => [
            'url' => "http://na01.alma.exlibrisgroup.com/view/uresolver/TR_INTEGRATION_INST/openurl?debug=true&u.ignore_date_coverage=true&rft.mms_id=9942811800561&rfr_id=info:sid/primo.exlibrisgroup.com&svc_dat=CTO",
            'rfr_id' => "vufind.svn.sourceforge.net",
            'resolver' => "alma",
            'window_settings' => "toolbar=no,location=no,directories=no,buttons=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=550,height=600",
            'show_in_results' => false,
            'show_in_record' => false,
            'show_in_holdings' => true,
            'embed' => true,
            'replace_other_urls' => true
        ],
    ];

    /**
     * Test
     *
     * @return void
     */
    public function testParseLinks()
    {
        $conn = $this->createConnector('alma.xml');

        $openUrl = "url_ver=Z39.88-2004&ctx_ver=Z39.88-2004";
        $result = $conn->parseLinks($conn->fetchLinks($openUrl));

        $testResult = [
            0 => [
                'title' => 'Ebook override',
                'coverage' => 'Available from 2019',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861830000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getFullTxt',
            ],
            1 => [
                'title' => 'ebrary Academic Complete Subscription UKI Edition',
                'coverage' => '',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861800000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getFullTxt',
            ],
            2 => [
                'title' => 'ebrary Science & Technology Subscription',
                'coverage' => '',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861790000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getFullTxt',
            ],
            3 => [
                'title' => 'EBSCOhost Academic eBook Collection (North America)',
                'coverage' => '',
                'access' => 'open',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861770000561&institutionId=561&customerId=550',
                'notes' => 'notessssssssssss SERVICE LEVEL PUBLIC NOTE',
                'authentication' => 'collection level auth SERVICE LEVEL AUTHE NOTE',
                'service_type' => 'getFullTxt',
            ],
            4 => [
                'title' => 'EBSCOhost eBook Community College Collection',
                'coverage' => '',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861780000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getHolding',
            ],
            5 => [
                'title' => 'Elsevier ScienceDirect Books',
                'coverage' => '',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861820000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getFullTxt',
            ],
            6 => [
                'title' => 'Request Assistance for this Resource!',
                'coverage' => '',
                'access' => '',
                'href' => 'https://www.google.com/search?Testingrft.oclcnum=437189463&q=Fundamental+Data+Compression&rft.archive=9942811800561',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getWebService',
            ],
            7 => [
                'title' => 'ProQuest Safari Tech Books Online',
                'coverage' => '',
                'access' => 'limited',
                'href' => 'https://na01.alma.exlibrisgroup.com/view/action/uresolver.do?operation=resolveService&package_service_id=5687861810000561&institutionId=561&customerId=550',
                'notes' => '',
                'authentication' => '',
                'service_type' => 'getFullTxt',
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
                '/../../../../../../tests/fixtures/resolver/response/' . $fixture
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
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";

        $client = new \Laminas\Http\Client();
        $client->setAdapter($adapter);

        $conn = new Alma($this->openUrlConfig['OpenURL']['url'], $client);
        return $conn;
    }
}
