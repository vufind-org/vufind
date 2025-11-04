<?php

/**
 * SOLR SimilarBuilder.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016-2018.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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

use function in_array;
use function strlen;

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
     * Boost multiplier for full string match when using the MoreLikeThis Handler
     *
     * @var string
     */
    protected $fullMatchBoostMultiplier = 10;

    /**
     * Characters that need to be escaped in a Solr query
     *
     * @var string
     */
    protected $escapedChars = '+-&|!(){}[]^"~*?:\\/';

    /**
     * Stop words that are ignored
     *
     * @var array
     */
    protected $stopWords = ['and', 'not', 'the'];

    /**
     * Whether to exclude other versions of the reference record from results
     *
     * @var bool
     */
    protected $excludeOtherVersions = false;

    /**
     * Constructor.
     *
     * @param ?\VuFind\Config\Config $searchConfig Search config
     * @param string                 $uniqueKey    Solr field used to store unique identifier
     *
     * @return void
     */
    public function __construct(
        ?\VuFind\Config\Config $searchConfig = null,
        $uniqueKey = 'id'
    ) {
        $this->uniqueKey = $uniqueKey;
        if (isset($searchConfig->MoreLikeThis)) {
            $mlt = $searchConfig->MoreLikeThis;
            if (!empty($mlt->useMoreLikeThisHandler)) {
                $this->useHandler = true;
                $this->handlerParams = $mlt->params ?? '';
            }
            if (isset($mlt->count)) {
                $this->count = $mlt->count;
            }
            if (isset($mlt->fullMatchBoostMultiplier)) {
                $this->fullMatchBoostMultiplier = $mlt->fullMatchBoostMultiplier;
            }
            $this->excludeOtherVersions = !empty($mlt->excludeOtherVersions);
        }
    }

    /// Public API

    /**
     * Build SOLR search parameters based on interesting terms.
     *
     * @param array    $record Interesting terms to use in the query
     * @param ParamBag $params Query parameters
     *
     * @return void
     */
    public function buildInterestingTermQuery(array $record, ParamBag $params): void
    {
        $boost = true;
        $settings = [];
        $specs = [
            'title^75',
            'title_short^100',
            'callnumber-label^400',
            'topic^300',
            'language^30',
            'author^75','publishDate',
        ];
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
        foreach ($settings as $field => $boostValue) {
            $count = 0;
            foreach ((array)($record[$field] ?? []) as $values) {
                if (strlen($values) < 3) {
                    continue;
                }
                $escaped = addcslashes($values, $this->escapedChars);
                $fullBoost = $this->fullMatchBoostMultiplier * $boostValue;
                $query[] = "$field:($escaped)^$fullBoost";
                $rest = explode(' ', $values);
                array_shift($rest);
                foreach ($rest as $value) {
                    if (strlen($value) < 3) {
                        continue;
                    }
                    $valueLower = mb_strtolower($value, 'UTF-8');
                    if (in_array($valueLower, $this->stopWords)) {
                        continue;
                    }
                    $escaped = addcslashes($value, $this->escapedChars);
                    $query[] = "$field:($escaped)^$boostValue";
                    if (++$count > 15) {
                        break;
                    }
                }
            }
        }
        if (!$query) {
            $queryStr = 'noproperinterestingtermsfound';
        } else {
            $query = array_unique($query);
            $queryStr = implode(' OR ', $query);
            if ($this->excludeOtherVersions) {
                // Filter out records with same work keys
                $parts = [];
                foreach ((array)($record['work_keys_str_mv'] ?? []) as $workKey) {
                    $workKey = addcslashes($workKey, $this->escapedChars);
                    $parts[] = "work_keys_str_mv:(\"$workKey\")";
                }
                if ($parts) {
                    $queryStr = "($queryStr) AND NOT ("
                        . implode(' OR ', $parts) . ')';
                }
            }
        }
        $params->set('q', $queryStr);

        if (null === $params->get('rows')) {
            $params->set('rows', $this->count);
        }
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
