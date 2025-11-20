<?php

/**
 * Class for accessing ProQuestFSG search API
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ProQuestFSG
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
 * ProQuestFSG SRU Search Interface
 *
 * @category VuFind
 * @package  ProQuestFSG
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Connector extends \VuFindSearch\Backend\SRU\Connector
{
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
     * @param \Laminas\Http\Client $client An HTTP client object
     * @param array                $config ProQuestFSG config
     */
    public function __construct(
        \Laminas\Http\Client $client,
        protected array $config
    ) {
        parent::__construct(
            'https://fedsearch.proquest.com/search/sru',
            $client
        );
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return array
     */
    public function getRecord($id, ?ParamBag $params = null)
    {
        $params ??= new ParamBag();
        $params->set('query', "rec.identifier = \"{$id}\"");
        return $this->search($params, 0, 1);
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return array
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        // Constant params
        $params->set('operation', 'searchRetrieve');
        $params->set('recordSchema', 'marcxml');

        $options = $params->getArrayCopy();

        // startRecord uses 1-based offsets
        $options['startRecord'] = $offset + 1;

        if (null !== $limit) {
            $options['maximumRecords'] = $limit;
        }

        $path = $this->defaultPath;
        foreach (($options['filters'] ?? []) as $filter) {
            [$filterKey, $filterValue] = explode(':', $filter, 2);
            if ('Databases' == $filterKey) {
                if (!$this->validateDatabaseValue($filterValue)) {
                    // This may happen in the context of a blended search,
                    // when the database is valid for another backend.
                    return ['docs' => [], 'offset' => 0, 'total' => 0];
                }
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

    /**
     * Checks whether a database code is valid for the ProQuestFSG API.
     *
     * @param string $value The database code to validate
     *
     * @return bool
     */
    protected function validateDatabaseValue(string $value)
    {
        if (!($this->config['Validation']['database_codes'] ?? false)) {
            return true;
        }

        // ProQuestFSG database product codes are all-lowercase (and underscore) strings
        if ($value != strtolower($value)) {
            $this->debug("Invalid database code '$value'; should skip ProQuestFSG query.");
            return false;
        }

        return true;
    }
}
