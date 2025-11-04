<?php

/**
 * Primo Central REST connector (REST API).
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace FinnaSearch\Backend\Primo;

use function array_key_exists;

/**
 * Primo Central connector (REST API).
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RestConnector extends \VuFindSearch\Backend\Primo\RestConnector
{
    /**
     * Hidden filters
     *
     * @var array
     */
    protected $hiddenFilters = [];

    /**
     * Set hidden filters
     *
     * @param array $filters Hidden filters
     *
     * @return void
     */
    public function setHiddenFilters($filters)
    {
        $this->hiddenFilters = $filters;
    }

    /**
     * Support method for query() -- perform inner search logic
     *
     * @param array $terms Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array $args  Associative array of optional arguments (see query method for more information)
     *
     * @throws \Exception
     * @return array       An array of query results
     */
    protected function performSearch($terms, $args)
    {
        $terms = $this->convertContainsOps($terms);

        // Strip 'pci.' prefix from citation searches:
        if (array_intersect(['citedby', 'citing'], array_column($args['filterList'] ?? [], 'field'))) {
            if (str_starts_with($terms[0]['lookfor'] ?? '', '"pci.')) {
                $terms[0]['lookfor'] = '"' . substr($terms[0]['lookfor'], 5);
            }
        }

        foreach ($this->hiddenFilters as $filter => $value) {
            if ($filter == 'pcAvailability') {
                // Toggle the setting unless we are told to ignore the hidden filter:
                if (empty($args['ignorePcAvailabilityHiddenFilter'])) {
                    $args['pcAvailability'] = (bool)$value;
                }
            } elseif ($filter == 'cdiFulltext') {
                // Toggle the setting unless it's already set:
                if (!array_key_exists('cdiFulltext', $args)) {
                    $args['cdiFulltext'] = (bool)$value;
                }
            } else {
                $args['filterList'][] = [
                    'field' => $filter,
                    'values' => (array)$value,
                    'facetOp' => 'AND',
                ];
            }
        }

        return parent::performSearch($terms, $args);
    }

    /**
     * Convert contains and contains_all to boolean searches
     *
     * @param array $terms Search terms
     *
     * @return array
     */
    protected function convertContainsOps(array $terms): array
    {
        $map = ['contains_all' => 'AND', 'contains' => 'OR'];

        // Regex for quoted words
        $pattern = '/"(.*?)"/';

        foreach ($terms as &$term) {
            if (isset($term['op']) && isset($map[$term['op']])) {
                $lookfor = trim($term['lookfor']);
                $op = $map[$term['op']] ?? 'AND';
                $words = $quoted = [];
                if (preg_match_all($pattern, $lookfor, $quoted)) {
                    // Search term includes quoted words, preserve them as groups.
                    $quoted = $quoted[0];
                    $unquoted = preg_replace($pattern, '', $lookfor);
                    $unquoted = preg_replace('/\s\s+/', ' ', $unquoted);
                    $unquoted = explode(' ', $unquoted);
                    $words = array_merge($unquoted, $quoted);
                } else {
                    // No quoted words in search term
                    $words = explode(' ', $lookfor);
                }
                $words = array_filter($words);

                $lookfor = implode(" $op ", $words);
                $term['op'] = 'contains';
                $term['lookfor'] = $lookfor;
            }
        }

        return $terms;
    }

    /**
     * Translate Primo's JSON into array of arrays.
     *
     * @param string $data   The raw xml from Primo
     * @param array  $params Request parameters
     *
     * @return array The processed response from Primo
     */
    protected function processResponse(string $data, array $params = []): array
    {
        $result = parent::processResponse($data, $params);

        // Parse API response
        $response = json_decode($data);

        $i = -1;
        foreach ($response->docs as $doc) {
            ++$i;
            // Set OpenURL
            if ($openUrl = $this->getOpenUrl($doc)) {
                $result['documents'][$i]['url'] = $openUrl;
            } else {
                unset($result['documents'][$i]['url']);
            }

            // Set any resource url
            foreach ($doc->delivery->link ?? [] as $link) {
                if (str_starts_with($link->linkType, 'http://purl.org/pnx/linkType/')) {
                    $linkType = substr($link->linkType, 29);
                } else {
                    $linkType = $link->linkType;
                }
                $result['documents'][$i]['resource_urls'][$linkType][] = [
                    'url' => $link->linkURL,
                    'label' => $link->displayLabel,
                ];
            }

            $result['documents'][$i]['date'] = $doc->pnx->addata->date ?? [];
            $result['documents'][$i]['open_access'] = !empty($doc->pnx->addata->oa);
            $result['documents'][$i]['sourceid'] = $doc->pnx->control->sourceid ?? [];

            // Prefix records id's
            $result['documents'][$i]['recordid'] = 'pci.' . $result['documents'][$i]['recordid'];
        }

        return $result;
    }

    /**
     * Retrieves a document specified by the ID.
     *
     * @param string $recordId  The document to retrieve from the Primo API
     * @param string $inst_code Institution code (optional)
     * @param bool   $onCampus  Whether the user is on campus
     *
     * @throws \Exception
     * @return string    The requested resource
     */
    public function getRecord($recordId, $inst_code = null, $onCampus = false)
    {
        if (str_starts_with($recordId, 'pci.')) {
            $recordId = substr($recordId, 4);
        }
        return parent::getRecord($recordId, $inst_code, $onCampus);
    }

    /**
     * Helper function for retrieving the OpenURL link from a Primo result.
     *
     * @param \StdClass $doc Result
     *
     * @throws \Exception
     * @return string|false
     */
    protected function getOpenUrl(\stdClass $doc)
    {
        foreach ($doc->delivery->link ?? [] as $link) {
            if ('http://purl.org/pnx/linkType/openurl' === $link->linkType) {
                $openurl = $link->linkURL;
            } elseif ('http://purl.org/pnx/linkType/openurlfulltext' === $link->linkType) {
                $openurlFullText = $link->linkURL;
            }
        }
        $result = $openurl ?? $openurlFullText ?? null;

        if (!$result) {
            if (($url = (string)($doc->delivery->GetIt2->link ?? '')) !== '') {
                $result = $url;
            } elseif (($url = (string)($doc->delivery->GetIt1[0]->links[0]->link ?? '')) !== '') {
                $result = $url;
            }
        }

        if ($result) {
            // Remove blocked and empty URL parameters
            $blocklist = ['rft_id' => 'info:oai/'];

            if (strstr($result, '?') === false) {
                return $result;
            }

            [$host, $query] = explode('?', $result);

            $params = [];
            foreach (explode('&', $query) as $param) {
                if (strstr($param, '=') === false) {
                    continue;
                }
                [$key, $val] = explode('=', $param, 2);
                $val = trim(urldecode($val));
                if (
                    '' === $val
                    || ($blocklist[$key] ?? '') === $val
                ) {
                    continue;
                }
                $params[$key] = $val;
            }
            $query = http_build_query($params);
            return "$host?$query";
        }

        return false;
    }
}
