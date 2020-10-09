<?php
/**
 * SOLR backend.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\Solr;

use FinnaSearch\Feature\WorkExpressionsInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends \VuFindSearch\Backend\Solr\Backend
    implements WorkExpressionsInterface
{
    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param int           $offset Search offset
     * @param int           $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(AbstractQuery $query, $offset, $limit,
        ParamBag $params = null
    ) {
        // Enforce a hard limit to avoid problems due to bad configuration
        if ($params->get('cursorMark')) {
            if ($limit > 1000) {
                $limit = 1000;
            }
        } elseif ($limit > 100) {
            $limit = 100;
        }
        return parent::search($query, $offset, $limit, $params);
    }

    /**
     * Return similar records.
     *
     * @param string   $id            Id of record to compare with
     * @param ParamBag $defaultParams Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function similar($id, ParamBag $defaultParams = null)
    {
        // Hack to work around Solr bugs in the MLT Handlers
        if ($this->getSimilarBuilder()->mltHandlerActive()) {
            // Fetch record first
            $params = new ParamBag();
            $this->injectResponseWriter($params);
            $response = $this->connector->retrieve($id, $params);
            $results = json_decode($response, true);
            if (!empty($results['response']['docs'][0])) {
                $params = $defaultParams ? clone $defaultParams : new ParamBag();
                $this->injectResponseWriter($params);
                $params->mergeWith(
                    $this->getSimilarBuilder()
                        ->buildInterestingTermQuery($results['response']['docs'][0])
                );
                $params->add('fq', sprintf('-id:"%s"', addcslashes($id, '"')));
                $response = $this->connector->search($params);
            }
        } else {
            $params = $defaultParams ? clone $defaultParams : new ParamBag();
            $this->injectResponseWriter($params);
            $params->mergeWith($this->getSimilarBuilder()->build($id, $params));
            $response = $this->connector->similar($id, $params);
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Return work expressions.
     *
     * @param string   $id            Id of record to compare with
     * @param array    $workKeys      Work identification keys
     * @param ParamBag $defaultParams Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function workExpressions($id, $workKeys, ParamBag $defaultParams = null)
    {
        $params = $defaultParams ? clone $defaultParams
            : new \VuFindSearch\ParamBag();
        $this->injectResponseWriter($params);
        $query = [];
        foreach ($workKeys as $key) {
            $key = addcslashes($key, '+-&|!(){}[]^"~*?:\\/');
            $query[] = "work_keys_str_mv:(\"$key\")";
        }
        $params->set('q', implode(' OR ', $query));
        $params->add('fq', sprintf('-id:"%s"', addcslashes($id, '"')));
        $params->add('rows', 100);
        $params->add('sort', 'main_date_str desc, title_sort asc');
        $response = $this->connector->search($params);
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }
}
