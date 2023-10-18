<?php

/**
 * Redi resolver driver test
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
use VuFind\Resolver\Driver\Redi;

/**
 * Redi resolver driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RediTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test-Config
     *
     * @var array
     */
    protected $openUrlConfig = [
        'OpenURL' => [
            'url' => 'http://www.redi-bw.de/links/ubl',
            'rfr_id' => 'www.ub.uni-leipzig.de',
            'resolver' => 'redi',
            'window_settings' => "toolbar=no,location=no,directories=no,buttons=no,status=no,menubar=no,'
                . 'scrollbars=yes,resizable=yes,width=550,height=600",
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
        $openUrl = $this->getFixture('openurl/redi');
        $result = $conn->parseLinks($conn->fetchLinks($openUrl));

        $testResult = [
            0 => [
                'title' => 'DOI:10.1007/s11606-014-2915-9',
                'href' => 'http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=citation'
                    . '&rl_link_name=doi'
                    . '&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7',
                'access' => 'unknown',
                'coverage' => null,
                'service_type' => 'getDOI',
             ],
            1 => [
                'title' => 'Zum Volltext (via SpringerLink)',
                'href' => 'http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=ezb'
                    . '&rl_link_name=0.article'
                    . '&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7',
                'access'        => 'limited',
                'coverage' => '',
                'service_type' => 'getFullTxt',
            ],
            2 => [
                'title' => 'Zur Zeitschriftenhomepage* (via www.ncbi.nlm.nih.gov)',
                'href' => 'http://www-fr.redi-bw.de/links/?rl_site=ubl&rl_action=link&rl_link_target=ezb'
                    . '&rl_link_name=1.article'
                    . '&rl_citation=9443914d0e261c0c1f6a3fd8151213c1d4cec05f5d3053097da6fa5597bbb9d7',
                'access'        => 'open',
                'coverage' => '*Es konnte nicht zuverlÃ¤ssig festgestellt werden, ob der gesuchte Aufsatz '
                    . 'in den Zeitraum fÃ¤llt, fÃ¼r den bei diesem Anbieter der Volltext verfÃ¼gbar ist.',
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
            $responseObj = HttpResponse::fromString(
                $this->getFixture("resolver/response/$fixture")
            );
            $adapter->setResponse($responseObj);
        }
        $client = new \Laminas\Http\Client();
        $client->setAdapter($adapter);

        $conn = new Redi($this->openUrlConfig['OpenURL']['url'], $client);
        return $conn;
    }
}
