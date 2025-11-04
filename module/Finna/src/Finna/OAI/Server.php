<?php

/**
 * OAI Server class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  OAI_Server
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OAI;

use VuFind\RecordDriver\AbstractBase as AbstractRecordDriver;

/**
 * OAI Server class
 *
 * This class provides OAI server functionality.
 *
 * @category VuFind
 * @package  OAI_Server
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Server extends \VuFind\OAI\Server
{
    /**
     * Finna specific api fields from [OAI] section
     *
     * @var array
     */
    protected array $finnaApiFields = [];

    /**
     * Finna metadata prefix
     *
     * @var string
     */
    protected const OAI_FINNA_JSON = 'oai_finna_json';

    /**
     * Does the current configuration support the Finna metadata format (using
     * the API's record formatter.
     *
     * @return bool
     */
    protected function supportsFinnaMetadata()
    {
        return !empty($this->finnaApiFields) && null !== $this->recordFormatter;
    }

    /**
     * Initialize data about metadata formats. (This is called on demand and is
     * defined as a separate method to allow easy override by child classes).
     *
     * @return void
     */
    protected function initializeMetadataFormats()
    {
        parent::initializeMetadataFormats();

        $this->metadataFormats['oai_ead'] = [
            'schema' => 'https://www.loc.gov/ead/ead.xsd',
            'namespace' => 'http://www.loc.gov/ead/'];
        $this->metadataFormats['oai_ead3'] = [
            'schema' => 'https://www.loc.gov/ead/ead3.xsd',
            'namespace' => 'http://ead3.archivists.org/schema/'];
        $this->metadataFormats['oai_forward'] = [
            'schema' => 'http://forward.cineca.it/schema/EN15907-forward-v1.0.xsd',
            'namespace' => 'http://project9forward.eu/schemas/EN15907-forward'];
        $this->metadataFormats['oai_lido'] = [
            'schema' => 'http://www.lido-schema.org/schema/v1.0/lido-v1.0.xsd',
            'namespace' => 'http://www.lido-schema.org/'];

        $qdc = 'http://dublincore.org/schemas/xmls/qdc/2008/02/11/qualifieddc.xsd';
        $this->metadataFormats['oai_qdc'] = [
            'schema' => $qdc,
            'namespace' => 'urn:dc:qdc:container'];
        if ($this->supportsFinnaMetadata()) {
            $this->metadataFormats[self::OAI_FINNA_JSON] = [
                'schema' => 'https://vufind.org/xsd/oai_vufind_json-1.0.xsd',
                'namespace' => 'http://vufind.org/oai_vufind_json-1.0',
            ];
        }
    }

    /**
     * Get record as a metadata presentation
     *
     * @param AbstractRecordDriver $record A record driver object
     * @param string               $format Metadata format to obtain
     *
     * @return string|bool String on success or false if error occurs
     */
    protected function getRecordAsXML(AbstractRecordDriver $record, string $format): string|false
    {
        if (self::OAI_FINNA_JSON === $format && $this->supportsFinnaMetadata()) {
            return $this->getFinnaMetadata($record);
        }
        return parent::getRecordAsXml($record, $format);
    }

    /**
     * Respond to a ListMetadataFormats request.
     *
     * @return string|false
     */
    protected function listMetadataFormats()
    {
        // If a specific ID was provided, try to load the related record; otherwise,
        // set $record to false so we know it is a generic request.
        if (isset($this->params['identifier'])) {
            if (!($record = $this->loadRecord($this->params['identifier']))) {
                return $this->showError('idDoesNotExist', 'Unknown Record');
            }
        } else {
            $record = false;
        }

        // Loop through all available metadata formats and see if they apply in
        // the current context (all apply if $record is false, since that
        // means that no specific record ID was requested; otherwise, they only
        // apply if the current record driver supports them):
        $response = $this->createResponse();
        $xml = $response->addChild('ListMetadataFormats');
        foreach ($this->getMetadataFormats() as $prefix => $details) {
            if (
                $record === false
                || $record->getXML($prefix) !== false
                || ('oai_vufind_json' === $prefix && $this->supportsVuFindMetadata())
                || (self::OAI_FINNA_JSON === $prefix && $this->supportsFinnaMetadata())
            ) {
                $node = $xml->addChild('metadataFormat');
                $node->metadataPrefix = $prefix;
                if (isset($details['schema'])) {
                    $node->schema = $details['schema'];
                }
                if (isset($details['namespace'])) {
                    $node->metadataNamespace = $details['namespace'];
                }
            }
        }

        // Display the response:
        return $response->asXML();
    }

    /**
     * Load data from the OAI section of config.ini. (This is called by the
     * constructor and is only a separate method to allow easy override by child
     * classes).
     *
     * @param \VuFind\Config\Config $config VuFind configuration
     *
     * @return void
     */
    protected function initializeSettings(\VuFind\Config\Config $config)
    {
        parent::initializeSettings($config);
        // Initialize Finna API format fields:
        $this->finnaApiFields = array_filter(
            explode(
                ',',
                $config->OAI->finna_api_format_fields ?? ''
            )
        );
    }

    /**
     * Support method for attachNonDeleted() to build the Finna metadata for
     * a record driver.
     *
     * @param object $record A record driver object
     *
     * @return string
     */
    protected function getFinnaMetadata($record)
    {
        // Root node
        $recordDoc = new \DOMDocument();
        $finnaFormat = $this->getMetadataFormats()[self::OAI_FINNA_JSON];
        $rootNode = $recordDoc->createElementNS($finnaFormat['namespace'], self::OAI_FINNA_JSON . ':record');
        $rootNode->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $finnaFormat['namespace'] . ' ' . $finnaFormat['schema']);
        $recordDoc->appendChild($rootNode);

        // Add oai_dc part
        $oaiDc = new \DOMDocument();
        $oaiDc->loadXML($record->getXML('oai_dc', $this->baseHostURL, $this->recordLinkerHelper));
        $rootNode->appendChild($recordDoc->importNode($oaiDc->documentElement, true));

        // Add Finna specific metadata
        $records = $this->recordFormatter->format([$record], $this->finnaApiFields);
        $metadataNode = $recordDoc->createElementNS($finnaFormat['namespace'], self::OAI_FINNA_JSON . ':metadata');
        $metadataNode->setAttribute('type', 'application/json');
        $metadataNode->appendChild($recordDoc->createCDATASection(json_encode($records[0])));
        $rootNode->appendChild($metadataNode);

        return $recordDoc->saveXML();
    }
}
