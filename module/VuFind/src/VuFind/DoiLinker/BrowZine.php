<?php
/**
 * BrowZine DOI linker
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  DOI
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace VuFind\DoiLinker;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindSearch\Backend\BrowZine\Connector;

/**
 * BrowZine DOI linker
 *
 * @category VuFind
 * @package  DOI
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class BrowZine implements DoiLinkerInterface, TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * BrowZine connector
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param Connector $connector Connector
     */
    public function __construct(Connector $connector)
    {
        $this->connector = $connector;
    }

    /**
     * Given an array of DOIs, perform a lookup and return an associative array
     * of arrays, keyed by DOI. Each array contains one or more associative arrays
     * with 'link' and 'label' keys.
     *
     * @param array $doiArray DOIs to look up
     *
     * @return array
     */
    public function getLinks(array $doiArray)
    {
        $response = [];
        foreach ($doiArray as $doi) {
            $data = $this->connector->lookupDoi($doi)['data'] ?? null;
            if (!empty($data['browzineWebLink'])) {
                $response[$doi][] = [
                    'link' => $data['browzineWebLink'],
                    'label' => $this->translate('View Complete Issue'),
                    'data' => $data,
                ];
            }
            if (!empty($data['fullTextFile'])) {
                $response[$doi][] = [
                    'link' => $data['fullTextFile'],
                    'label' => $this->translate('PDF Full Text'),
                    'data' => $data,
                ];
            }
        }
        return $response;
    }
}
