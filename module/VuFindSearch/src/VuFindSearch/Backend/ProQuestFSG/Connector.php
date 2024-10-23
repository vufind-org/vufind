<?php

/**
 * Class for accessing OCLC WorldCat search API
 *
 * PHP version 8
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

namespace VuFindSearch\Backend\ProQuestFSG;

use VuFindSearch\ParamBag;

use function intval;
use function strval;

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
     * Additional options
     *
     * @var array
     */
    protected $options;

    /**
     * The version to specify in the URL
     *
     * @var string
     */
    protected $sruVersion = '1.2';

    /**
     * The default path, if a particular database target is not indicated.
     *
     * @var string
     */
    protected $defaultPath = '/all_subscribed';

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client  An HTTP client object
     * @param array                $options Additional config settings
     */
    public function __construct(
        \Laminas\Http\Client $client,
        array $options = []
    ) {
        parent::__construct(
            'https://fedsearch.proquest.com/search/sru',
            $client
        );
        $this->options = $options;
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
        $params->set('query', "rec.identifier = \"{$id}\"");
        return $this->search($params, 1, 1);
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
        // Constant params
        $params->set('operation', 'searchRetrieve');
        $params->set('recordSchema', 'marcxml');

        $options = $params->getArrayCopy();
        $options['startRecord'] = $offset;
        if (null !== $limit) {
            $options['maximumRecords'] = $limit;
        }

        $path = $this->defaultPath;
        foreach (($options['filters'] ?? []) as $filter) {
            [$filterKey, $filterValue] = explode(':', $filter, 2);
            if ('Databases' == $filterKey) {
                $path = '/' . $filterValue;
            } else {
                $filterRelationValue = $filterValue ?
                    '=' . $filterValue :
                    '=1';
                $filterString = " and ({$filterKey}{$filterRelationValue})";
                $options['query'][0] .= $filterString;
            }
        }
        unset($options['filters']);

        $sortKey = $params->get('sortKey')[0] ?? null;
        if (null !== $sortKey) {
            $options['query'][0] .= " sortBy {$sortKey}";
            unset($options['sortKey']);
        }

        $response = $this->call('GET', $path, $options, true);

        $finalDocs = [];
        foreach ($response->record as $doc) {
            $finalDocs[] = $doc->asXML();
        }

        $databases = [];
        foreach (($response->Facets->Databases->Database ?? []) as $database) {
            $databases[] = [
                'id' => strval($database->databaseId),
                'code' => strval($database->databaseCode),
                'name' => strval($database->databaseName),
                'count' => intval($database->numberOfRecords),
            ];
        }
        $facets = [
            'Databases' => $databases,
        ];

        return [
            'docs' => $finalDocs,
            'offset' => $offset,
            'total' => (int)($response->RecordCount),
            'facets' => $facets,
        ];
    }
}
