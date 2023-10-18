<?php

/**
 * Jop resolver driver test
 *
 * PHP version 8
 *
 * Copyright (C) Leipzig University Library 2015.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Resolver\Driver;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Test as TestAdapter;
use Laminas\Http\Response as HttpResponse;
use VuFind\Resolver\Driver\Jop;

/**
 * Jop resolver driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class JopTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test-Config
     *
     * @var array
     */
    protected $openUrlConfig = [
        'OpenURL' => [
            'url' => 'http://services.d-nb.de/fize-service/gvr/full.xml',
            'rfr_id' => 'www.ub.uni-leipzig.de',
            'resolver' => 'jop',
            'window_settings' => 'toolbar=no,location=no,directories=no,buttons=no,status=no,'
                . 'menubar=no,scrollbars=yes,resizable=yes,width=550,height=600',
            'show_in_results' => false,
            'show_in_record' => false,
            'show_in_holdings' => true,
            'embed' => true,
            'replace_other_urls' => true,
        ],
    ];

    /**
     * Test link parsing
     *
     * @return void
     */
    public function testParseLinks()
    {
        $conn = $this->createConnector('jop.xml');

        $openUrl = $this->getFixture('openurl/jop');
        $result = $conn->parseLinks($conn->fetchLinks($openUrl));

        $testResult = [
            0 => [
                'title' => 'Noûs : a Quarterly Journal of Philosophy (1997-)',
                'coverage' => 'ab Vol. 31, Iss. 1 (1997)',
                'access' => 'limited',
                'href' => 'http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1468-0068',
                'service_type' => 'getFullTxt',
            ],
            1 => [
                'title' => 'Noûs (ältere Jahrgänge via JSTOR)',
                'coverage' => 'ab Vol. 1, Iss. 1 (1967); für die Ausgaben der aktuellen 11 Jahrgänge nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://www.jstor.org/action/showPublication?journalCode=nous',
                'service_type' => 'getFullTxt',
            ],
            2 => [
                'title' => 'Nous (via EBSCO Host)',
                'coverage' => 'für die Ausgaben der vergangenen 12 Monate nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://search.ebscohost.com/direct.asp?db=aph&jid=D97&scope=site',
                'service_type' => 'getFullTxt',
            ],
            3 => [
                'title' => 'Nous (via EBSCO Host)',
                'coverage' => 'für die Ausgaben der vergangenen 12 Monate nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://search.ebscohost.com/direct.asp?db=lfh&jid=D97&scope=site',
                'service_type' => 'getFullTxt',
            ],
            4 => [
                'title' => 'Philosophical Perspectives (aktuelle Jahrgänge)',
                'coverage' => 'ab Vol. 17 (2003)',
                'access' => 'limited',
                'href' => 'http://onlinelibrary.wiley.com/journal/10.1111/%28ISSN%291520-8583',
                'service_type' => 'getFullTxt',
            ],
            5 => [
                'title' => 'Print available',
                'coverage' => 'Philosophical perspectives; Leipzig UB; Nachweis als Serie',
                'access' => 'open',
                'href' => 'http://dispatch.opac.dnb.de/CHARSET=ISO-8859-1/DB=1.1/CMD'
                    . '?ACT=SRCHA&IKT=8509&SRT=LST_ty&TRM=IDN+011960027+or+IDN+01545794X'
                    . '&HLIB=009030085#009030085',
                'service_type' => 'getHolding',
            ],
            6 => [
                'title' => 'Print available',
                'coverage' => 'Noûs; Leipzig UB // HB/FH/ Standortsignatur: 96-7-558; '
                    . 'CA 5470 Magazin: 96-7-558; 1.1967 - 27.1993; 30.1996 - 43.2009; Letzten 15 Jg. Freihand',
                'access' => 'open',
                'service_type' => 'getHolding',
            ],
        ];

        $this->assertEquals($result, $testResult);
    }

    /**
     * Test URL generation
     *
     * @return void
     */
    public function testGetResolverUrl()
    {
        $ipAddr = '1.2.3.4';
        $connector = $this->createConnector(null, $ipAddr);
        $expected = 'http://services.d-nb.de/fize-service/gvr/full.xml?'
            . 'foo=bar&pid=client_ip%3D' . $ipAddr;
        $this->assertEquals(
            $expected,
            $connector->getResolverUrl('foo=bar')
        );
    }

    /**
     * Test implicit downgrade of open url
     *
     * @return void
     */
    public function testDowngradeOpenUrl()
    {
        $ipAddr = '1.2.3.4';
        $connector = $this->createConnector(null, $ipAddr);
        $expected = 'http://services.d-nb.de/fize-service/gvr/full.xml?'
            . 'genre=article'
            . '&date=2022-07-14'
            . '&issn=0123456789'
            . '&isbn=9876543210'
            . '&volume=I'
            . '&issue=test'
            . '&spage=1'
            . '&pages=9'
            . '&pid=client_ip%3D' . $ipAddr;
        $this->assertEquals(
            $expected,
            $connector->getResolverUrl(
                'ctx_ver=Z39.88-2004'
                . '&rft.date=2022-07-14'
                . '&rft.issn=0123456789'
                . '&rft.isbn=9876543210'
                . '&rft.volume=I'
                . '&rft.issue=test'
                . '&rft.spage=1'
                . '&rft.pages=9'
            )
        );
    }

    /**
     * Test implicit call of downgradeOpenUrl
     *
     * @return void
     */
    public function testDowngradeOpenUrlWithoutMappingKey()
    {
        $ipAddr = '1.2.3.4';
        $connector = $this->createConnector(null, $ipAddr);
        $expected = 'http://services.d-nb.de/fize-service/gvr/full.xml?'
            . 'genre=article&pid=client_ip%3D' . $ipAddr;
        $this->assertEquals(
            $expected,
            $connector->getResolverUrl('ctx_ver=Z39.88-2004')
        );
    }

    /**
     * Create connector with fixture file.
     *
     * @param string $fixture Fixture file
     * @param string $ipAddr  Source IP address to simulate
     *
     * @return Connector
     *
     * @throws InvalidArgumentException Fixture file does not exist
     */
    protected function createConnector($fixture = null, $ipAddr = '127.0.0.1')
    {
        $adapter = new TestAdapter();
        if ($fixture) {
            $responseObj = HttpResponse::fromString(
                $this->getFixture("resolver/response/$fixture")
            );
            $adapter->setResponse($responseObj);
        }
        $client = new \Laminas\Http\Client();
        $client->setAdapter($adapter);

        $ipReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $ipReader->expects($this->once())->method('getUserIp')
            ->will($this->returnValue($ipAddr));
        $conn = new Jop($this->openUrlConfig['OpenURL']['url'], $client, $ipReader);
        return $conn;
    }
}
