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
use VuFindSearch\Backend\BrowZine\Command\LookupDoiCommand;
use VuFindSearch\Service;

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
     * Search service
     *
     * @var Service
     */
    protected $searchService;

    /**
     * Configuration options
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Service $searchService Search service
     * @param array   $config        Configuration settings
     */
    public function __construct(Service $searchService, array $config = [])
    {
        $this->searchService = $searchService;
        $this->config = $config;
    }

    /**
     * Check if an array key is available in the data and allowed by filter settings.
     *
     * @param string $key  Key to check
     * @param array  $data Available data
     *
     * @return bool
     */
    protected function arrayKeyAvailable(string $key, ?array $data): bool
    {
        if (empty($data[$key])) {
            return false;
        }
        switch (strtolower(trim($this->config['filterType'] ?? 'none'))) {
        case 'include':
            return in_array($key, (array)($this->config['filter'] ?? []));
        case 'exclude':
            return !in_array($key, (array)($this->config['filter'] ?? []));
        default:
        }
        // If we got this far, no filter setting is applied, so the option is legal:
        return true;
    }

    /**
     * Given an array of DOIs, perform a lookup and return an associative array
     * of arrays, keyed by DOI. Each array contains one or more associative arrays
     * with required 'link' (URL to related resource) and 'label' (display text)
     * keys and an optional 'icon' (URL to icon graphic) key.
     *
     * @param array $doiArray DOIs to look up
     *
     * @return array
     */
    public function getLinks(array $doiArray)
    {
        $baseIconUrl = 'https://assets.thirdiron.com/images/integrations/';
        $response = [];
        foreach ($doiArray as $doi) {
            $command = new LookupDoiCommand('BrowZine', $doi);
            $result = $this->searchService->invoke($command)->getResult();
            $data = $result['data'] ?? null;
            if ($this->arrayKeyAvailable('browzineWebLink', $data)) {
                $response[$doi][] = [
                    'link' => $data['browzineWebLink'],
                    'label' => $this->translate('View Complete Issue'),
                    'icon' => $baseIconUrl . 'browzine-open-book-icon.svg',
                    'data' => $data,
                ];
            }
            if ($this->arrayKeyAvailable('fullTextFile', $data)) {
                $response[$doi][] = [
                    'link' => $data['fullTextFile'],
                    'label' => $this->translate('PDF Full Text'),
                    'icon' => $baseIconUrl . 'browzine-pdf-download-icon.svg',
                    'data' => $data,
                ];
            }
        }
        return $response;
    }
}
