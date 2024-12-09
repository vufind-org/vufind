<?php

/**
 * Model for MARC records without a fullrecord in Solr. The fullrecord is being
 * retrieved from an external source.
 *
 * PHP version 8
 *
 * Copyright (C) Leipzig University Library 2014.
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
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Ulf Seltmann <seltmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver;

use Laminas\Log\LoggerAwareInterface as LoggerAwareInterface;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface;

use function sprintf;

/**
 * Model for MARC records without a fullrecord in Solr. The fullrecord is being
 * retrieved from an external source.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Ulf Seltmann <seltmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 * @link     https://vufind.org/wiki/configuration:remote_marc_records
 */
class SolrMarcRemote extends SolrMarc implements
    HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Holds the URI-Pattern of the service that returns the marc binary blob by id
     *
     * @var string
     */
    protected $uriPattern = '';

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit
     * for built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration
     * file (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration
     * file
     *
     * @throws \Exception
     */
    public function __construct(
        $mainConfig = null,
        $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);

        // get config values for remote fullrecord service
        $this->uriPattern = $mainConfig->Record->remote_marc_url ?? null;
        if (!$this->uriPattern) {
            throw new \Exception('SolrMarcRemote baseUrl-setting missing.');
        }
    }

    /**
     * Get access to the MarcReader object.
     *
     * @return MarcReader
     */
    public function getMarcReader()
    {
        $this->verifyFullRecordIsAvailable();
        return parent::getMarcReader();
    }

    /**
     * Load the fullrecord field if not already loaded
     *
     * @return void
     */
    protected function verifyFullRecordIsAvailable()
    {
        // handle availability of fullrecord
        if (!isset($this->fields['fullrecord'])) {
            // retrieve fullrecord from external source
            if (!isset($this->fields['id'])) {
                throw new \Exception(
                    'No unique id given for fullrecord retrieval'
                );
            }
            $this->fields['fullrecord']
                = $this->getRemoteFullrecord($this->fields['id']);
        }
    }

    /**
     * Retrieves the full Marcrecord from a remote service defined by uriPattern
     *
     * @param String $id - this record's unique identifier
     *
     * @return bool|string
     * @throws \Exception
     */
    protected function getRemoteFullrecord($id)
    {
        if (empty($id)) {
            throw new \Exception('empty id given');
        }

        if (empty($this->uriPattern)) {
            throw new \Exception('no Marc-Server configured');
        }

        $url = sprintf($this->uriPattern, $id);

        try {
            $response = $this->httpService->get($url);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        if (!$response->isSuccess()) {
            $this->debug(
                'HTTP status ' . $response->getStatusCode() .
                ' received, retrieving data for record: ' . $id
            );
            return false;
        }

        return $response->getBody();
    }
}
