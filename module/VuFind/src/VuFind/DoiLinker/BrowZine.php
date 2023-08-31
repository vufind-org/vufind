<?php

/**
 * BrowZine DOI linker
 *
 * PHP version 8
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

use function in_array;

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
     * Configured DOI services
     *
     * @var array
     */
    protected $doiServices;

    /**
     * Constructor
     *
     * @param Service $searchService Search service
     * @param array   $config        Configuration settings
     * @param array   $doiServices   Configured DOI services
     */
    public function __construct(
        Service $searchService,
        array $config = [],
        array $doiServices = []
    ) {
        $this->searchService = $searchService;
        $this->config = $config;
        $this->doiServices = $doiServices;
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
     * keys and an optional 'icon' (URL to icon graphic) or localIcon (name of
     * configured icon in theme) key.
     *
     * @param array $doiArray DOIs to look up
     *
     * @return array
     */
    public function getLinks(array $doiArray)
    {
        $response = [];
        $localIcons = !empty($this->config['local_icons']);
        foreach ($doiArray as $doi) {
            $command = new LookupDoiCommand('BrowZine', $doi);
            $result = $this->searchService->invoke($command)->getResult();
            $data = $result['data'] ?? null;
            foreach ($this->getDoiServices() as $key => $config) {
                if ($this->arrayKeyAvailable($key, $data)) {
                    $result = [
                        'link' => $data[$key],
                        'label' => $this->translate($config['linkText']),
                        'data' => $data,
                    ];
                    if (!$localIcons && !empty($config['icon'])) {
                        $result['icon'] = $config['icon'];
                    } else {
                        $result['localIcon'] = $config['localIcon'];
                    }
                    $response[$doi][] = $result;
                }
            }
        }
        return $response;
    }

    /**
     * Get an array of DOI services and their configuration
     *
     * @return array
     */
    protected function getDoiServices(): array
    {
        if (empty($this->doiServices)) {
            $baseIconUrl = 'https://assets.thirdiron.com/images/integrations/';
            return [
                'browzineWebLink' => [
                    'linkText' => 'View Complete Issue',
                    'localIcon' => 'browzine-issue',
                    'icon' => $baseIconUrl . 'browzine-open-book-icon.svg',
                ],
                'fullTextFile' => [
                    'linkText' => 'PDF Full Text',
                    'localIcon' => 'browzine-pdf',
                    'icon' => $baseIconUrl . 'browzine-pdf-download-icon.svg',
                ],
            ];
        }
        $result = [];
        foreach ($this->doiServices as $key => $config) {
            $parts = explode('|', $config);
            $result[$key] = [
                'linkText' => $parts[0],
                'localIcon' => $parts[1],
                'icon' => $parts[2] ?? null,
            ];
        }
        return $result;
    }
}
