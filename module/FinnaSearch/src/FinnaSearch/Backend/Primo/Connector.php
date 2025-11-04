<?php

/**
 * Primo Central connector.
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

use function count;

/**
 * Primo Central connector.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Connector extends \VuFindSearch\Backend\Primo\Connector
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
     * @param string $institution Institution
     * @param array  $terms       Associative array:
     *     index       string: primo index to search (default "any")
     *     lookfor     string: actual search terms
     * @param array  $args        Associative array of optional arguments:
     *     phrase      bool:   true if it's a quoted phrase (default false)
     *     onCampus    bool:   (default true)
     *     didyoumean  bool:   (default false)
     *     filterList  array:  (field, value) pairs to filter results (def null)
     *     pageNumber  string: index of first record (default 1)
     *     limit       string: number of records to return (default 20)
     *     sort        string: value to be used by for sorting (default null)
     *     returnErr   bool:   false to fail on error; true to return empty
     *                         empty result set with an error field (def true)
     *     Anything in $args   not listed here will be ignored.
     *
     * Note: some input parameters accepted by Primo are not implemented here:
     *  - dym (did you mean)
     *  - highlight
     *  - more (get more)
     *  - lang (specify input language so engine can do lang. recognition)
     *  - displayField (has to do with highlighting somehow)
     *
     * @throws \Exception
     * @return array             An array of query results
     */
    protected function performSearch($institution, $terms, $args)
    {
        $map = ['contains_all' => 'AND', 'contains' => 'OR'];

        // Regex for quoted words
        $pattern = '/"(.*?)"/';

        foreach ($terms as &$term) {
            if (isset($term['op']) && isset($map[$term['op']])) {
                $lookfor = trim($term['lookfor']);
                $op = $map[$term['op']];
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
        foreach ($this->hiddenFilters as $filter => $value) {
            if ($filter == 'pcAvailability') {
                // Toggle the setting unless we are told to ignore the hidden filter:
                if (empty($args['ignorePcAvailabilityHiddenFilter'])) {
                    $args['pcAvailability'] = (bool)$value;
                }
            } elseif ($filter == 'cdiFulltext') {
                // Not supported!
            } else {
                $args['filterList'][] = [
                    'field' => $filter,
                    'values' => (array)$value,
                    'facetOp' => 'AND',
                ];
            }
        }
        return parent::performSearch($institution, $terms, $args);
    }

    /**
     * Translate Primo's XML into array of arrays.
     *
     * @param string $data   The raw xml from Primo
     * @param array  $params Request parameters
     *
     * @return array The processed response from Primo
     */
    protected function process($data, $params = [])
    {
        $result = parent::process($data, $params);

        // Load API content as XML objects
        $sxe = new \SimpleXMLElement($data);

        if ($sxe === false) {
            throw new \Exception('Error while parsing the document');
        }

        // Register the 'sear' namespace at the top level to avoid problems:
        $sxe->registerXPathNamespace(
            'sear',
            'http://www.exlibrisgroup.com/xsd/jaguar/search'
        );

        // Get the available namespaces. The Primo API uses multiple namespaces.
        // Will be used to navigate the DOM for elements that have namespaces
        $namespaces = $sxe->getNameSpaces(true);

        $docset = $sxe->xpath('//sear:DOC');
        if (empty($docset) && isset($sxe->JAGROOT->RESULT->DOCSET->DOC)) {
            $docset = $sxe->JAGROOT->RESULT->DOCSET->DOC;
        }

        for ($i = 0; $i < count($docset); $i++) {
            $doc = $docset[$i];

            // Due to a bug in the primo API, the first result has
            //   a namespace (prim:) while the rest of the results do not.
            //   Those child elements do not get added to $doc.
            //   If the bib parent element (PrimoNMBib) is missing for a $doc,
            //   that means it has the prim namespace prefix.
            // So first set the right prefix
            $prefix = $doc;
            if ($doc->PrimoNMBib != 'true' && isset($namespaces['prim'])) {
                // Use the namespace prefix to get those missing child
                //   elements out of $doc.
                $prefix = $doc->children($namespaces['prim']);
            }

            // Set OpenURL
            $sear = $doc->children($namespaces['sear']);
            if ($openUrl = $this->getOpenUrl($sear)) {
                $result['documents'][$i]['url'] = $openUrl;
            } else {
                unset($result['documents'][$i]['url']);
            }

            // Set any resource url
            // Get the URL, which has a separate namespace
            foreach ((array)($prefix->PrimoNMBib->record->links ?? []) as $type => $urls) {
                foreach ((array)$urls as $urlField) {
                    $parts = explode('$$', (string)$urlField);
                    $url = '';
                    $label = '';
                    foreach ($parts as $part) {
                        if (str_starts_with($part, 'U')) {
                            $url = substr($part, 1);
                        } elseif (str_starts_with($part, 'E')) {
                            $label = substr($part, 1);
                        }
                    }
                    if (!$url || !parse_url($url, PHP_URL_HOST)) {
                        continue;
                    }
                    $result['documents'][$i]['resource_urls'][$type][] = [
                        'url' => $url,
                        'label' => $label,
                    ];
                }
            }

            $result['documents'][$i]['date'] = [];
            foreach ($prefix->PrimoNMBib->record->facets->creationdate ?? [] as $date) {
                $result['documents'][$i]['date'][] = (string)$date;
            }
            $result['documents'][$i]['open_access']
                = ((string)$prefix->PrimoNMBib->record->display->oa === 'free_for_read');
            $result['documents'][$i]['sourceid'] = [];
            foreach ($prefix->PrimoNMBib->control->sourceid ?? [] as $sourceid) {
                $result['documents'][$i]['sourceid'][] = (string)$sourceid;
            }
            $result['documents'][$i]['doi'] = [];
            foreach ($prefix->PrimoNMBib->record->addata->doi ?? [] as $doi) {
                $result['documents'][$i]['doi'][] = (string)$doi;
            }
            $result['documents'][$i]['peer_reviewed']
                = ((string)$prefix->PrimoNMBib->record->display->lds50 ?? '') === 'peer_reviewed';

            // Prefix records id's
            $result['documents'][$i]['recordid']
                = 'pci.' . $result['documents'][$i]['recordid'];
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
        [, $recordId] = explode('.', $recordId, 2);
        return parent::getRecord($recordId, $inst_code, $onCampus);
    }

    /**
     * Retrieves multiple documents specified by the ID.
     *
     * @param array  $recordIds The documents to retrieve from the Primo API
     * @param string $inst_code Institution code (optional)
     * @param bool   $onCampus  Whether the user is on campus
     *
     * @throws \Exception
     * @return string    The requested resource
     */
    public function getRecords($recordIds, $inst_code = null, $onCampus = false)
    {
        $recordIds = array_map(
            function ($recordId) {
                [, $recordId] = explode('.', $recordId, 2);
                return $recordId;
            },
            $recordIds
        );
        return parent::getRecords($recordIds, $inst_code, $onCampus);
    }

    /**
     * Helper function for retrieving the OpenURL link from a Primo result.
     *
     * @param SimpleXmlElement $sear XML-element to search
     *
     * @throws \Exception
     * @return string|false
     */
    protected function getOpenUrl($sear)
    {
        $result = null;
        if (!empty($sear->LINKS->openurl)) {
            if (($url = $sear->LINKS->openurl) !== '') {
                $result = (string)$url;
            }
        }

        $attr = $sear->GETIT->attributes();
        if (!empty($attr->GetIt2)) {
            if (($url = (string)$attr->GetIt2) !== '') {
                $result = $url;
            }
        }

        if (!empty($attr->GetIt1)) {
            if (($url = (string)$attr->GetIt1) !== '') {
                $result = $url;
            }
        }

        if ($result) {
            // Remove blacklisted and empty URL parameters
            $blacklist = ['rft_id' => 'info:oai/'];

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
                    $val == ''
                    || isset($blacklist[$key]) && $blacklist[$key] == $val
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
