<?php

/**
 * Simple JSON-based record collection.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\Solr\Response\Json;

/**
 * Simple JSON-based record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection
    extends \VuFindSearch\Backend\Solr\Response\Json\RecordCollection
{
    /**
     * Get query debug information
     *
     * @return array
     */
    public function getDebugInformation()
    {
        return isset($this->response['debug']) ? $this->response['debug'] : [];
    }

    /**
     * Extract the best matching Spellcheck query from the raw Solr input parameters.
     *
     * @return string
     */
    protected function getSpellcheckQuery()
    {
        $params = $this->getSolrParameters();
        return isset($params['spellcheck.q'])
            ? $params['spellcheck.q']
            : (isset($params['q']) ? $params['q'] : '');
    }

    /**
     * Get raw Solr Spellcheck suggestions.
     *
     * @return array
     */
    protected function getRawSpellcheckSuggestions()
    {
        $query = $this->getSpellcheckQuery();
        if (str_word_count($query) > 1
            && isset($this->response['spellcheck']['collations'])
        ) {
            // Compose a list that resembles Solr's single-word suggestions
            $suggestions = [];
            foreach ($this->response['spellcheck']['collations'] as $collation) {
                if ($collation[0] != 'collation') {
                    continue;
                }
                $suggestions[] = [
                    'word' => $collation[1],
                    'freq' => 0
                ];
            }
            return [[
                $query,
                [
                    'numFound' => count($suggestions),
                    'origFreq' => 0,
                    'suggestion' => $suggestions
                ]
            ]];
        }
        return isset($this->response['spellcheck']['suggestions'])
            ? $this->response['spellcheck']['suggestions'] : [];
    }
}
