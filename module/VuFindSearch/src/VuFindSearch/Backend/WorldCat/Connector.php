<?php

/**
 * Class for accessing OCLC WorldCat search API
 *
 * PHP version 7
 *
 * Copyright (C) Andrew Nagy 2008.
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
 * @package  WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindSearch\Backend\WorldCat;

use VuFindSearch\ParamBag;

/**
 * WorldCat SRU Search Interface
 *
 * @category VuFind
 * @package  WorldCat
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Connector extends \VuFindSearch\Backend\SRU\Connector
{
    /**
     * OCLC API key
     *
     * @var string
     */
    protected $wskey;

    /**
     * Additional options
     *
     * @var array
     */
    protected $options;

    /**
     * Constructor
     *
     * @param string               $wsKey   Web services key
     * @param \Laminas\Http\Client $client  An HTTP client object
     * @param array                $options Additional config settings
     */
    public function __construct(
        $wsKey,
        \Laminas\Http\Client $client,
        array $options = []
    ) {
        parent::__construct(
            'http://www.worldcat.org/webservices/catalog/search/sru',
            $client
        );
        $this->wskey = $wsKey;
        $this->options = $options;
    }

    /**
     * Get holdings information for the specified record.
     *
     * @param string $id Record to obtain holdings for.
     *
     * @throws \Exception
     * @return \SimpleXMLElement
     */
    public function getHoldings($id)
    {
        $this->client->resetParameters();
        if (!isset($this->options['useFrbrGroupingForHoldings'])) {
            $grouping = 'on';   // default to "on" for backward compatibility
        } else {
            $grouping = $this->options['useFrbrGroupingForHoldings'] ? 'on' : 'off';
        }
        $uri = "http://www.worldcat.org/webservices/catalog/content/libraries/{$id}"
            . "?wskey={$this->wskey}&servicelevel=full&frbrGrouping=$grouping";
        if (isset($this->options['latLon'])) {
            [$lat, $lon] = explode(',', $this->options['latLon']);
            $uri .= '&lat=' . urlencode($lat) . '&lon=' . urlencode($lon);
        }
        $this->client->setUri($uri);
        $this->debug('Connect: ' . $uri);
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        return simplexml_load_string($result->getBody());
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return string    MARC XML
     */
    public function getRecord($id, ParamBag $params = null)
    {
        $params = $params ?: new ParamBag();
        $params->set('servicelevel', 'full');
        $params->set('wskey', $this->wskey);

        $this->client->resetParameters();
        $uri = 'http://www.worldcat.org/webservices/catalog/content/' . $id;
        $uri .= '?' . implode('&', $params->request());
        $this->client->setUri($uri);
        $this->debug('Connect: ' . $uri);
        $result = $this->client->setMethod('POST')->send();
        $this->checkForHttpError($result);

        // Check for error message in response:
        $body = $result->getBody();
        $xml = simplexml_load_string($body);
        $error = isset($xml->diagnostic);

        return [
            'docs' => $error ? [] : [$body],
            'offset' => 0,
            'total' => $error ? 0 : 1,
        ];
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return string
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        $params->set('startRecord', $offset);
        $params->set('maximumRecords', $limit);
        $params->set('servicelevel', 'full');
        $params->set('wskey', $this->wskey);

        $response = $this->call('POST', $params->getArrayCopy(), false);

        $xml = simplexml_load_string($response);
        $docs = $xml->records->record ?? [];
        $finalDocs = [];
        foreach ($docs as $doc) {
            $finalDocs[] = $doc->recordData->asXML();
        }
        return [
            'docs' => $finalDocs,
            'offset' => $offset,
            'total' => (int)($xml->numberOfRecords ?? 0),
        ];
    }
}
