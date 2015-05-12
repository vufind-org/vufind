<?php
/**
 * Model for MARC records without a fullrecord in Solr. The fullrecord is being
 * retrieved from an external source.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Ulf Seltmann <seltmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFindHttp\HttpServiceAwareInterface as HttpServiceAwareInterface,
    Zend\Log\LoggerAwareInterface as LoggerAwareInterface;

/**
 * Model for MARC records without a fullrecord in Solr. The fullrecord is being
 * retrieved from an external source.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Ulf Seltmann <seltmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrMarcRemote extends SolrMarc implements
    HttpServiceAwareInterface, LoggerAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * MARC record. Access only via getMarcRecord() as this is initialized lazily.
     *
     * @var \File_MARC_Record
     */
    protected $lazyMarcRecord = null;

    /**
     * Holds the URI-Pattern of the service that returns the marc binary blob by id
     *
     * @var string
     */
    protected $uriPattern = '';

    /**
     * Holds config.ini data
     *
     * @var array
     */
    protected $mainConfig;

    /**
     * Holds searches.ini data
     *
     * @var array
     */
    protected $searchesConfig;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     *
     * @throws \Exception
     */
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null
    ) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);

        if (!isset($recordConfig->General)) {
            throw new \Exception('SolrMarcRemote General settings missing.');
        }

        // get config values for remote fullrecord service
        if (! $recordConfig->General->get('baseUrl')) {
            throw new \Exception('SolrMarcRemote baseUrl-setting missing.');
        } else {
            $this->uriPattern = $recordConfig->General->get('baseUrl');
        }

        $this->mainConfig = $mainConfig;
        $this->searchesConfig = $searchSettings;
    }

    /**
     * Get access to the raw File_MARC object.
     *
     * @return \File_MARCBASE
     * @throws \Exception
     * @throws \File_MARC_Exception
     */
    public function getMarcRecord()
    {
        if (null === $this->lazyMarcRecord) {
            // handle availability of fullrecord
            if (isset($this->fields['fullrecord'])) {
                // standard Vufind2-behaviour

                // also process the MARC record:
                $marc = trim($this->fields['fullrecord']);

            } else {
                // fallback: retrieve fullrecord from external source

                if (! isset($this->fields['id'])) {
                    throw new \Exception(
                        'No unique id given for fullrecord retrieval'
                    );
                }

                $marc = $this->getRemoteFullrecord($this->fields['id']);

            }

            if (isset($marc)) {
                // continue with standard Vufind2-behaviour if marcrecord is present

                // check if we are dealing with MARCXML
                if (substr($marc, 0, 1) == '<') {
                    $marc = new \File_MARCXML($marc, \File_MARCXML::SOURCE_STRING);
                } else {
                    // When indexing over HTTP, SolrMarc may use entities instead of
                    // certain control characters; we should normalize these:
                    $marc = str_replace(
                        ['#29;', '#30;', '#31;'], ["\x1D", "\x1E", "\x1F"], $marc
                    );
                    $marc = new \File_MARC($marc, \File_MARC::SOURCE_STRING);
                }

                $this->lazyMarcRecord = $marc->next();
                if (!$this->lazyMarcRecord) {
                    throw new \File_MARC_Exception('Cannot Process MARC Record');
                }

            } else {
                // no marcrecord was found

                throw new \Exception(
                    'no Marc was found neither on the marc server ' .
                    'nor in the solr-record for id ' . $this->fields['id']
                );
            }
        }

        return $this->lazyMarcRecord;
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
