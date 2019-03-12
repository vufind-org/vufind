<?php
/**
 * OAI Server class
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  OAI_Server
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\OAI;

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
    }
}
