<?php

/**
 * BrowZine identifier linker
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018-2025.
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
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */

namespace VuFind\IdentifierLinker;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindSearch\Backend\BrowZine\Command\LookupDoiCommand;
use VuFindSearch\Backend\BrowZine\Command\LookupIssnsCommand;
use VuFindSearch\Service;

use function count;
use function in_array;
use function is_array;

/**
 * BrowZine identifier linker
 *
 * @category VuFind
 * @package  IdentifierLinker
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:identifier_linkers Wiki
 */
class BrowZine implements IdentifierLinkerInterface, TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param Service $searchService       Search service
     * @param array   $config              Configuration settings
     * @param array   $doiServices         Configured DOI services
     * @param array   $issnServices        Configured ISSN services
     * @param array   $bestIntegratorLinks Configuration for bestIntegratorLinks
     */
    public function __construct(
        protected Service $searchService,
        protected array $config = [],
        protected array $doiServices = [],
        protected array $issnServices = [],
        protected array $bestIntegratorLinks = []
    ) {
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
     * Format a single service link, or return null if it should not be displayed.
     *
     * @param array  $data       Raw API response data
     * @param string $serviceKey Key being extracted from response
     * @param array  $config     Service-specific configuration settings
     *
     * @return ?array{link: string, label: string, data: array, localIcon: ?string, icon: ?string, linkType: ?string}
     */
    protected function processServiceLink(array $data, string $serviceKey, array $config): ?array
    {
        $serviceData = $data[$serviceKey];
        $result = [
            'link' => $serviceData,
            'data' => $data,
        ];

        // If this link is actually the 'bestIntegratorLink' array, extract the appropriate
        // text and icon config from it.
        if ('bestIntegratorLink' == $serviceKey) {
            $result['link'] = $serviceData['bestLink'] ?? $result['link'];

            $linkType = $serviceData['linkType'] ?? null;
            $specificConfig = $this->getBestIntegratorLinks()[$linkType] ?? false;
            // False means there is no specific config; use the bestIntegratorLink default.
            // Non-empty array means actually use this specific config.
            // Empty array means this integrator link type is disabled.
            if (is_array($specificConfig)) {
                $config = $specificConfig;
                if (empty($config)) {
                    return null;
                }
            }
            if ($this->config['useBrowzineLabel'] ?? false) {
                $config['linkText'] = $serviceData['recommendedLinkText'] ?? $config['linkText'];
            }
        }

        $result['label'] = $this->translate($config['linkText']);
        $localIcons = !empty($this->config['local_icons']);
        if (!$localIcons && !empty($config['icon'])) {
            $result['icon'] = $config['icon'];
        } else {
            $result['localIcon'] = $config['localIcon'];
        }
        $result['linkType'] = $linkType ?? $serviceKey;
        return $result;
    }

    /**
     * Given an array of identifier arrays, perform a lookup and return an associative array
     * of arrays, matching the keys of the input array. Each output array contains one or more
     * associative arrays with required 'link' (URL to related resource) and 'label' (display text)
     * keys and an optional 'icon' (URL to icon graphic) or localIcon (name of configured icon in
     * theme) key.
     *
     * @param array[] $idArray Identifiers to look up
     *
     * @return array
     */
    public function getLinks(array $idArray): array
    {
        $response = [];
        foreach ($idArray as $idKey => $ids) {
            // If we have a DOI, that gets priority because it is more specific; otherwise we'll
            // fall back and attempt the ISSN:
            if (isset($ids['doi']) && ($doiServices = $this->getDoiServices())) {
                $command = new LookupDoiCommand('BrowZine', $ids['doi']);
                $result = $this->searchService->invoke($command)->getResult();
                $data = $result['data'] ?? [];
                $response += $this->getLinksByType($data, $idKey, $doiServices);
            } elseif (isset($ids['issn']) && ($issnServices = $this->getIssnServices())) {
                $command = new LookupIssnsCommand('BrowZine', $ids['issn']);
                $result = $this->searchService->invoke($command)->getResult();
                $data = $result['data'][0] ?? [];
                $response += $this->getLinksByType($data, $idKey, $issnServices);
            }
        }
        return $response;
    }

    /**
     * Helper method for getLinks. Generate links by link type.
     *
     * @param array  $data     Response data from search service
     * @param string $idKey    Identifier key
     * @param array  $services Configured services by link type
     *
     * @return array An array of link type to an array of links.
     */
    protected function getLinksByType(array $data, string $idKey, array $services): array
    {
        $links = [];
        foreach ($services as $serviceKey => $config) {
            if (
                $this->arrayKeyAvailable($serviceKey, $data) &&
                $serviceLink = $this->processServiceLink($data, $serviceKey, $config)
            ) {
                $links[] = $serviceLink;
            }
        }
        return $links ? [$idKey => $links] : [];
    }

    /**
     * Unpack service configuration into more useful array format.
     *
     * @param array $config Raw (pipe-delimited) configuration from BrowZine.ini
     *
     * @return array
     */
    protected function unpackServiceConfig(array $config): array
    {
        $result = [];
        foreach ($config as $key => $configLine) {
            if (empty($configLine)) {
                $result[$key] = [];
            } else {
                $parts = explode('|', $configLine);
                $result[$key] = count($parts) < 2 ? [] : [
                    'linkText' => $parts[0],
                    'localIcon' => $parts[1],
                    'icon' => $parts[2] ?? null,
                ];
            }
        }
        return $result;
    }

    /**
     * Get an array of DOI services and their configuration
     *
     * @return array
     */
    protected function getDoiServices(): array
    {
        return $this->unpackServiceConfig($this->doiServices);
    }

    /**
     * Get an array of ISSN services and their configuration
     *
     * @return array
     */
    protected function getIssnServices(): array
    {
        return $this->unpackServiceConfig($this->issnServices);
    }

    /**
     * Get an array of configuration for 'bestIntegratorLink' values.
     *
     * @return array
     */
    protected function getBestIntegratorLinks(): array
    {
        return $this->unpackServiceConfig($this->bestIntegratorLinks);
    }
}
