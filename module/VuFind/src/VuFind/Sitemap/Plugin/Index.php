<?php

/**
 * Index-based generator plugin
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\Sitemap\Plugin;

/**
 * Index-based generator plugin
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Index extends AbstractGeneratorPlugin
{
    /**
     * Base URL for site
     *
     * @var string
     */
    protected $baseUrl  = '';

    /**
     * Settings specifying which backends to index.
     *
     * @var array
     */
    protected $backendSettings;

    /**
     * Helper for fetching IDs from the search service.
     *
     * @var Index\AbstractIdFetcher
     */
    protected $idFetcher;

    /**
     * Page size for data retrieval
     *
     * @var int
     */
    protected $countPerPage;

    /**
     * Search filters
     *
     * @var string[]
     */
    protected $filters;

    /**
     * Constructor
     *
     * @param array                   $backendSettings Settings specifying which
     * backends to index
     * @param Index\AbstractIdFetcher $idFetcher       The helper object for
     * retrieving IDs
     * @param int                     $countPerPage    Page size for data retrieval
     * @param string[]                $filters         Search filters
     */
    public function __construct(
        array $backendSettings,
        Index\AbstractIdFetcher $idFetcher,
        int $countPerPage,
        array $filters = []
    ) {
        $this->backendSettings = $backendSettings;
        $this->idFetcher = $idFetcher;
        $this->countPerPage = $countPerPage;
        $this->filters = $filters;
    }

    /**
     * Get the name of the sitemap used to create the sitemap file. This will be
     * appended to the configured base name, and may be blank to use the base
     * name without a suffix.
     *
     * @return string
     */
    public function getSitemapName(): string
    {
        return '';
    }

    /**
     * Generate urls for the sitemap.
     *
     * May yield a string per URL or an array that defines lastmod in addition to url.
     *
     * @return \Generator
     */
    public function getUrls(): \Generator
    {
        // Initialize variables for message displays within the loop below:
        $currentPage = $recordCount = 0;

        // Loop through all backends
        foreach ($this->backendSettings as $current) {
            $recordUrl = $this->baseUrl . $current['url'];
            $this->verboseMsg(
                'Adding records from ' . $current['id']
                . " with record base url $recordUrl"
            );
            $offset = $this->idFetcher->getInitialOffset();
            $this->idFetcher->setupBackend($current['id']);
            while (true) {
                $result = $this->idFetcher->getIdsFromBackend(
                    $current['id'],
                    $offset,
                    $this->countPerPage,
                    $this->filters
                );
                foreach ($result['ids'] as $index => $item) {
                    $loc = htmlspecialchars($recordUrl . urlencode($item));
                    if (!str_contains($loc, 'http')) {
                        $loc = 'http://' . $loc;
                    }
                    $recordCount++;
                    if (isset($result['lastmods'][$index])) {
                        yield ['url' => $loc, 'lastmod' => $result['lastmods'][$index]];
                    } else {
                        yield $loc;
                    }
                }
                $currentPage++;
                $this->verboseMsg("Page $currentPage, $recordCount processed");
                if (!isset($result['nextOffset'])) {
                    break;
                }
                $offset = $result['nextOffset'];
            }
        }
    }

    /**
     * Set plugin options.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options): void
    {
        parent::setOptions($options);
        $this->baseUrl = $options['baseUrl'] ?? '';
    }
}
