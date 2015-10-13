<?php
/**
 * Redi resolver driver test
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Resolver\Driver;
use VuFind\Resolver\Driver\Redi;

use Zend\Http\Client\Adapter\Test as TestAdapter;
use Zend\Http\Response as HttpResponse;

use InvalidArgumentException;

/**
 * Redi resolver driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class RediTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test-Config
     *
     * @var array
     */
    protected $openUrlConfig = [
        'OpenURL' => [
            'url' => "http://www.redi-bw.de/links/ubl",
            'rfr_id' => "www.ub.uni-leipzig.de",
            'resolver' => "redi",
            'window_settings' => "toolbar=no,location=no,directories=no,buttons=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=550,height=600",
            'show_in_results' => false,
            'show_in_record' => false,
            'show_in_holdings' => true,
            'embed' => true,
            'replace_other_urls' => true,
        ],
    ];

    /**
     * Test
     *
     * @return void
     */
    public function testParseLinks()
    {
        $conn = $this->createConnector('redi.xhtml');
        $openUrl = "url_ver=Z39.88-2004&ctx_ver=Z39.88-2004&ctx_enc=info%3Aofi%2Fenc%3AUTF-8&rfr_id=info%3Asid%2Fwww.ub.uni-leipzig.de%3Agenerator&rft.title=Are+ACOs+on+Uncertain+Ethical+Ground+and+a+Threat+to+the+Autonomy+of+Patients+and+Physicians%3F&rft.date=2014-07-03&genre=article&rft_id=info%3Adoi%2F10.1007%2Fs11606-014-2915-9&issn=1525-1497&volume=29&issue=10&spage=1319&epage=1321&pages=1319-1321&jtitle=J+GEN+INTERN+MED&atitle=Are+ACOs+on+Uncertain+Ethical+Ground+and+a+Threat+to+the+Autonomy+of+Patients+and+Physicians%3F&aulast=Lindsey&aufirst=Gene&rft.language%5B0%5D=eng";
        $result = $conn->parseLinks($conn->fetchLinks($openUrl));

        $testResult = [
            0 => [
                'title' => "DOI:10.1007/s11606-014-2915-9",
                'href' => "http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=citation&rl_link_name=doi&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7",
                'service_type' => "getFullTxt",
             ],
            1 => [
                'title' => "Zum Volltext (via SpringerLink)",
                'href' => "http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=ezb&rl_link_name=0.article&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7",
                'access'        => 'limited',
                'coverage' => "",
                'service_type' => "getFullTxt",
            ],
            2 => [
                'title' => "Zur Zeitschriftenhomepage* (via www.ncbi.nlm.nih.gov)",
                'href' => "http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=ezb&rl_link_name=1.article&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7",
                'access'        => 'open',
                'coverage' => "*Es konnte nicht zuverlÃ¤ssig festgestellt werden, ob der gesuchte Aufsatz in den Zeitraum fÃ¤llt, fÃ¼r den bei diesem Anbieter der Volltext verfÃ¼gbar ist.",
                'service_type' => "getFullTxt",
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
        $client = new \Zend\Http\Client();
        $client->setAdapter($adapter);

        $conn = new Redi($this->openUrlConfig['OpenURL']['url'], $client);
        return $conn;
    }
}
