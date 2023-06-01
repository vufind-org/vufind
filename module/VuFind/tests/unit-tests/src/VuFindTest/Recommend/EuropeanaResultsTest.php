<?php

/**
 * EuropeanaResults tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Recommend;

use Laminas\Http\Client\Adapter\Test as TestAdapter;
use VuFind\Recommend\EuropeanaResults;
use VuFindHttp\HttpService;

/**
 * EuropeanaResults tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EuropeanaResultsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test that the module properly parses a sample response.
     *
     * This is a bare minimum test to confirm that Laminas\Feed deals with the RSS
     * response correctly. More work should be done to confirm that URL generation
     * works appropriately, optional configuration parameters are respected, etc.
     *
     * @return void
     */
    public function testResponseParsing()
    {
        $europeana = new EuropeanaResults('fakekey');
        $europeana->setHttpService($this->getHttpService());
        $europeana->setConfig(''); // use defaults
        $results = $this->getMockResults();
        $query = new \Laminas\Stdlib\Parameters(['lookfor' => 'test']);
        $europeana->init($results->getParams(), $query);
        $europeana->process($results);
        $expectedBaseLink = 'http://www.europeana.eu/portal/record/92099';
        $this->assertEquals(
            [
                'worksArray' => [
                    [
                        'title' => 'Guiard des Moulins , Petite Bible historiale de Charles V. [Paris, '
                            . 'BnF, MSS Français 5707]',
                        'link' => $expectedBaseLink . '/BibliographicResource_2000068736886.html',
                        'enclosure' => null,
                    ],
                    [
                        'title' => 'Guiard des Moulins , Bible Historiale de Jean de Berry. [Paris, BnF, '
                            . 'MSS Français 20090]',
                        'link' => $expectedBaseLink . '/BibliographicResource_2000060239235.html',
                        'enclosure' => null,
                    ],
                    [
                        'title' => 'Saint Augustin , De civitate Dei (Livres XI-XXII) , traduit en français '
                            . 'par Raoul de Presle. [Paris, BnF, MSS Français 173]',
                        'link' => $expectedBaseLink . '/BibliographicResource_1000157170726.html',
                        'enclosure' => null,
                    ],
                    [
                        'title' => 'Saint Augustin , La cité de Dieu [De Civitate Dei] , (Livres XI-XXII), '
                            . 'traduit en français par Raoul de Presles. [Paris, BnF, MSS Français 174]',
                        'link' => $expectedBaseLink . '/BibliographicResource_1000157170711.html',
                        'enclosure' => null,
                    ],
                    [
                        'title' => 'Saint Augustin , De Civitate Dei , traduit en français par Raoul de Presles '
                            . '(Livre I-X). [Paris, BnF, MSS Français 22912]',
                        'link' => $expectedBaseLink . '/BibliographicResource_1000157170710.html',
                        'enclosure' => null,
                    ],
                ],
                'feedTitle' => 'Europeana.eu',
                'sourceLink' =>
                    'http://www.europeana.eu/portal/search.html?query=test',
            ],
            $europeana->getResults()
        );
    }

    /**
     * Return connector.
     *
     * @param string $fixture HTTP response fixture to load (optional)
     *
     * @return HttpClient
     */
    protected function getHttpService($fixture = 'europeana')
    {
        $adapter = new TestAdapter();
        if ($fixture) {
            $adapter->setResponse($this->getFixture("recommend/$fixture"));
        }
        $service = new HttpService();
        $service->setDefaultAdapter($adapter);
        return $service;
    }

    /**
     * Get a mock results object.
     *
     * @param \VuFind\Search\Solr\Params $params Params to include in container.
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults($params = null)
    {
        if (null === $params) {
            $params = $this->getMockParams();
        }
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Get a mock params object.
     *
     * @param \VuFindSearch\Query\Query $query Query to include in container.
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = null)
    {
        if (null === $query) {
            $query = new \VuFindSearch\Query\Query('foo', 'bar');
        }
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getQuery')
            ->will($this->returnValue($query));
        return $params;
    }
}
