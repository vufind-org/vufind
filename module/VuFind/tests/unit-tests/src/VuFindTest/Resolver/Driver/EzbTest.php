<?php
/**
 * Ezb resolver driver test
 *
 * PHP version 7
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

use VuFind\Resolver\Driver\Ezb;
use Zend\Http\Client\Adapter\Test as TestAdapter;

use Zend\Http\Response as HttpResponse;

/**
 * Ezb resolver driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class EzbTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test-Config
     *
     * @var array
     */
    protected $openUrlConfig = [
        'OpenURL' => [
            'url' => "http://services.d-nb.de/fize-service/gvr/full.xml",
            'rfr_id' => "www.ub.uni-leipzig.de",
            'resolver' => "ezb",
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
        $conn = $this->createConnector('ezb.xml');

        $openUrl = "url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fwww.ub.uni-leipzig.de%3Agenerator&rft.title=No%C3%BBs&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&rft.creator=&rft.pub=Wiley-Blackwell&rft.format=Journal&rft.language=English&rft.issn=0029-4624&zdbid=339287-9";
        $result = $conn->parseLinks($conn->fetchLinks($openUrl));

        $testResult = [
            0 => [
                'title' => 'Noûs : a Quarterly Journal of Philosophy (1997-)',
                'coverage' => 'ab Vol. 31, Iss. 1 (1997)',
                'access' => 'limited',
                'href' => 'http://onlinelibrary.wiley.com/journal/10.1111/(ISSN)1468-0068',
                'service_type' => 'getFullTxt'
            ],
            1 => [
                'title' => 'Noûs (ältere Jahrgänge via JSTOR)',
                'coverage' => 'ab Vol. 1, Iss. 1 (1967); für die Ausgaben der aktuellen 11 Jahrgänge nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://www.jstor.org/action/showPublication?journalCode=nous',
                'service_type' => 'getFullTxt'
            ],
            2 => [
                'title' => 'Nous (via EBSCO Host)',
                'coverage' => 'für die Ausgaben der vergangenen 12 Monate nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://search.ebscohost.com/direct.asp?db=aph&jid=D97&scope=site',
                'service_type' => 'getFullTxt'
            ],
            3 => [
                'title' => 'Nous (via EBSCO Host)',
                'coverage' => 'für die Ausgaben der vergangenen 12 Monate nicht verfügbar',
                'access' => 'limited',
                'href' => 'http://search.ebscohost.com/direct.asp?db=lfh&jid=D97&scope=site',
                'service_type' => 'getFullTxt'
            ],
            4 => [
                'title' => 'Philosophical Perspectives (aktuelle Jahrgänge)',
                'coverage' => 'ab Vol. 17 (2003)',
                'access' => 'limited',
                'href' => 'http://onlinelibrary.wiley.com/journal/10.1111/%28ISSN%291520-8583',
                'service_type' => 'getFullTxt'
            ],
            5 => [
                'title' => 'Print available',
                'coverage' => 'Philosophical perspectives; Leipzig UB; Nachweis als Serie',
                'access' => 'open',
                'href' => 'http://dispatch.opac.dnb.de/CHARSET=ISO-8859-1/DB=1.1/CMD?ACT=SRCHA&IKT=8509&SRT=LST_ty&TRM=IDN+011960027+or+IDN+01545794X&HLIB=009030085#009030085',
                'service_type' => 'getHolding'
            ],
            6 => [
                'title' => 'Print available',
                'coverage' => 'Noûs; Leipzig UB // HB/FH/ Standortsignatur: 96-7-558; CA 5470 Magazin: 96-7-558; 1.1967 - 27.1993; 30.1996 - 43.2009; Letzten 15 Jg. Freihand',
                'access' => 'open',
                'service_type' => 'getHolding'
            ]
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

        $client = new \Zend\Http\Client();
        $client->setAdapter($adapter);

        $conn = new Ezb($this->openUrlConfig['OpenURL']['url'], $client);
        return $conn;
    }
}
