<?php
/**
 * Index-based generator plugin
 *
 * PHP version 7
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

use VuFindSearch\Service as SearchService;

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
     * Search service
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Settings specifying which backends to index.
     *
     * @var array
     */
    protected $backendSettings;

    /**
     * The class of the command for retrieving IDs
     *
     * @var string
     */
    protected $commandClass;

    /**
     * Page size for data retrieval
     *
     * @var int
     */
    protected $countPerPage;

    /**
     * Constructor
     *
     * @param SearchService $searchService   Search service
     * @param array         $backendSettings Settings specifying which backends to
     * index
     * @param string        $commandClass    The class of the command for retrieving
     * IDs
     * @param int           $countPerPage    Page size for data retrieval
     */
    public function __construct(
        SearchService $searchService,
        array $backendSettings,
        string $commandClass,
        int $countPerPage
    ) {
        $this->searchService = $searchService;
        $this->backendSettings = $backendSettings;
        $this->commandClass = $commandClass;
        $this->countPerPage = $countPerPage;
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
     * @return \Generator
     */
    public function getUrls(): \Generator
    {
        // Initialize variables for use within the loop below:
        $currentPage = 1;
        $recordCount = 0;

        // Loop through all backends
        foreach ($this->backendSettings as $current) {
            $recordUrl = $this->baseUrl . $current['url'];
            $this->verboseMsg(
                'Adding records from ' . $current['id']
                . " with record base url $recordUrl"
            );
            $offset = null;
            while (true) {
                $context = compact('currentPage', 'offset') + [
                    'countPerPage' => $this->countPerPage,
                ];
                $command = new $this->commandClass(
                    $current['id'],
                    $context,
                    $this->searchService
                );
                $this->searchService->invoke($command);
                $result = $command->getResult();
                if (empty($result['ids'])) {
                    break;
                }
                foreach ($result['ids'] as $item) {
                    $loc = htmlspecialchars($recordUrl . urlencode($item));
                    if (strpos($loc, 'http') === false) {
                        $loc = 'http://' . $loc;
                    }
                    $recordCount++;
                    yield $loc;
                }
                $offset = $result['nextOffset'];
                $this->verboseMsg("Page $currentPage, $recordCount processed");
                $currentPage++;
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
