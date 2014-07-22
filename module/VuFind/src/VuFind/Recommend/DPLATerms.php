<?php
/**
 * DPLATerms Recommendations Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * DPLATerms Recommendations Module
 *
 * This class uses current search terms to query the DPLA API.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
class DPLATerms implements RecommendInterface
{

    /**
     * Config
     *
     * @var \VuFind\Config
     */
    protected $config;

    /**
     * Vufind HTTP Client
     *
     * @var \VuFindHttp\HttpServiceInterface
     */
    protected $client;

    /**
     * Setting of initial collapsedness
     *
     * @var boolean
     */
    protected $collapsed;

    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $searchObject;

    /**
     * Constructor
     *
     * @param VufindConfig $config config.ini
     * @param VuFindHttp   $client VuFind HTTP client
     */
    public function __construct($config, $client)
    {
        $this->config = $config->DPLA;
        $this->client = $client;
    }

    /**
     * setConfig
     *
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $this->collapsed = filter_var($settings, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Abstract-required method
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed.
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getResults()
    {
        // Extract the first search term from the search object:
        $search = $this->searchObject->getParams()->getQuery();
        $filters = $this->searchObject->getParams()->getFilters();
        $lookfor = ($search instanceof \VuFindSearch\Query\Query)
            ? $search->getString()
            : '';
        $formatMap = array(
            'authorStr'           => 'sourceResource.creator',
            'building'            => 'provider.name',
            'format'              => 'sourceResource.format',
            'geographic_facet'    => 'sourceResource.spatial.region',
            'institution'         => 'provider.name',
            'language'            => 'sourceResource.language.name',
            'publishDate'         => 'sourceResource.date.begin',
            //'genre_facet'         => 'Genre',
            //'hierarchy_top_title' => 'Collection',
            //'callnumber-first'    => 'Call Number',
            //'dewey-hundreds'      => 'Call Number',
        );
        $returnFields = array(
            'id',
            'dataProvider',
            'sourceResource.title',
            'sourceResource.description',
        );
        $params = array(
            'q' => $lookfor,
            'fields' => implode(',', $returnFields),
            'api_key' => $this->config->apiKey
        );
        foreach($filters as $field=>$filter) {
            if (isset($formatMap[$field])) {
                $params[$formatMap[$field]] = implode(',', $filter);
            }
        }

        $this->client->setUri('http://api.dp.la/v2/items');
        $this->client->setMethod('GET');
        $this->client->setParameterGet($params);
        $response = $this->client->send();

        $body = json_decode($response->getBody());

        if ($body->count == 0) {
            return array();
        }

        $results = array();
        $title = 'sourceResource.title';
        $desc = 'sourceResource.description';
        foreach ($body->docs as $doc) {
            $results[] = array(
                'title' => is_array($doc->$title)
                    ? current($doc->$title)
                    : $doc->$title,
                'provider' => is_array($doc->dataProvider)
                    ? current($doc->dataProvider)
                    : $doc->dataProvider,
                'link' => 'http://dp.la/item/'.$doc->id
            );
            if (isset($doc->$desc)) {
                $results['desc'] = is_array($doc->$desc)
                    ? current($doc->$desc)
                    : $doc->$desc;
            }
        }

        return $results;
    }

    /**
     * Return the list of facets configured to be collapsed
     *
     * @return array
     */
    public function isCollapsed()
    {
        return $this->collapsed;
    }
}