<?php

/**
 * SOLR SimilarBuilder.
 *
 * PHP version 5
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\Solr;

use VuFindSearch\ParamBag;

/**
 * SOLR SimilarBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SimilarBuilder extends \VuFindSearch\Backend\Solr\SimilarBuilder
{
    /**
     * Solr field used to store unique identifier
     *
     * @var string
     */
    protected $uniqueKey;

    /**
     * Whether to use MoreLikeThis Handler instead of the traditional MoreLikeThis
     * component.
     *
     * @var bool
     */
    protected $useHandler = false;

    /**
     * MoreLikeThis Handler parameters
     *
     * @var string
     */
    protected $handlerParams = '';

    /**
     * Number of similar records to retrieve
     *
     * @var int
     */
    protected $count = 5;

    /**
     * Constructor.
     *
     * @param \Zend\Config\Config $searchConfig Search config
     * @param string              $uniqueKey    Solr field used to store unique
     * identifier
     *
     * @return void
     */
    public function __construct(\Zend\Config\Config $searchConfig = null,
        $uniqueKey = 'id'
    ) {
        $this->uniqueKey = $uniqueKey;
        if (isset($searchConfig->MoreLikeThis)) {
            $mlt = $searchConfig->MoreLikeThis;
            if (isset($mlt->useMoreLikeThisHandler)
                && $mlt->useMoreLikeThisHandler
            ) {
                $this->useHandler = true;
                $this->handlerParams = isset($mlt->params) ? $mlt->params : '';
            }
            if (isset($mlt->count)) {
                $this->count = $mlt->count;
            }
        }
    }

    /// Public API

    /**
     * Return SOLR search parameters based on interesting terms.
     *
     * @param array $interestingTerms Interesting terms to use in the query
     *
     * @return ParamBag
     */
    public function buildInterestingTermQuery($interestingTerms)
    {
        $params = new ParamBag();

        $boost = true;
        $settings = [];
        $specs = 'title^75,title_short^100,callnumber-label^400,topic^300'
            . ',language^30,author^75,publishDate';
        if ($this->handlerParams) {
            if (preg_match('/boost=([^\s]+)/', $this->handlerParams, $matches)) {
                $boost = $matches[1] === 'true';
            }
            if (preg_match('/qf=([^\s]+)/', $this->handlerParams, $matches)) {
                $specs = explode(',', $matches[1]);
            }
        }
        foreach ($specs as $spec) {
            $values = explode('^', $spec, 2);
            if ($boost && isset($values[1])) {
                $settings[$values[0]] = $values[1];
            } else {
                $settings[$spec] = 1;
            }
        }
        $query = [];
        foreach ($interestingTerms as $term) {
            list($field, $value) = explode(':', $term[0], 2);
            if (isset($settings[$field])) {
                $boostValue = round($settings[$field] * $term[1]);
                $query[] = "$field:($value)^$boostValue";
            }
        }
        $params->set('q', implode(' OR ', $query));

        if (null === $params->get('rows')) {
            $params->set('rows', $this->count);
        }

        return $params;
    }

    /**
     * Return true if MLT handler is being used (as opposed to the traditional MLT
     * component).
     *
     * @return bool
     */
    public function mltHandlerActive()
    {
        return $this->useHandler;
    }
}
