<?php
/**
 * Abstract command to generate a sitemap from a backend (if supported).
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFind\Sitemap\Command;

use VuFind\Sitemap\Generator;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Service;

/**
 * Abstract command to generate a sitemap from a backend (if supported).
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractGenerateSitemapCommand
    extends \VuFindSearch\Command\AbstractBase
{
    /**
     * Search service
     *
     * @var Service
     */
    protected $searchService;

    /**
     * CallMethodCommand constructor.
     *
     * @param string  $backend       Search backend identifier
     * @param mixed   $context       Command context
     * @param Service $searchService Search service
     */
    public function __construct(string $backend, $context, Service $searchService)
    {
        parent::__construct($backend, $context);
        $this->searchService = $searchService;
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        if (!($backend instanceof Backend)) {
            throw new \Exception('Unsupported backend: ' . get_class($backend));
        }
        $context = $this->getContext();
        $this->result = $this->generateForBackend(
            $backend,
            $context['recordUrl'],
            $context['currentPage'],
            $context['countPerPage'],
            $context['languages'],
            $context['generator']
        );
        return parent::execute($backend);
    }

    /**
     * Generate sitemap files for a single search backend.
     *
     * @param Backend   $backend      Search backend
     * @param string    $recordUrl    Base URL for record links
     * @param int       $currentPage  Sitemap page number to start generating
     * @param int       $countPerPage Page size
     * @param string[]  $languages    Supported languages
     * @param Generator $generator    Sitemap generator
     *
     * @return int                    Next sitemap page number to generate
     */
    protected function generateForBackend(
        Backend $backend,
        string $recordUrl,
        int $currentPage,
        int $countPerPage,
        array $languages,
        Generator $generator
    ) {
        $generator->verboseMsg(
            'Adding records from ' . $backend->getIdentifier()
            . " with record base url $recordUrl"
        );

        // Initialize values for loop:
        $currentOffset = $this->getInitialOffset();
        $recordCount = 0;

        $this->setupBackend($backend);
        while (true) {
            // Get IDs and break out of the loop if we've run out:
            $result = $this
                ->getIdsFromBackend($backend, $currentOffset, $countPerPage);
            if (empty($result['ids'])) {
                break;
            }
            $currentOffset = $result['nextOffset'];

            // Write the current entry:
            $smf = $generator->getNewSitemap();
            foreach ($result['ids'] as $item) {
                $loc = htmlspecialchars($recordUrl . urlencode($item));
                if (strpos($loc, 'http') === false) {
                    $loc = 'http://' . $loc;
                }
                if ($languages) {
                    $smf->addUrl(
                        [
                            'url' => $loc,
                            'languages' => $languages
                        ]
                    );
                } else {
                    $smf->addUrl($loc);
                }
            }
            $filename = $generator->getFilenameForPage($currentPage);
            if (false === $smf->write($filename)) {
                throw new \Exception("Problem writing $filename.");
            }

            // Update total record count:
            $recordCount += count($result['ids']);

            $generator->verboseMsg("Page $currentPage, $recordCount processed");

            // Update counter:
            $currentPage++;
        }
        return $currentPage;
    }

    /**
     * Get the initial offset to seed the search process
     *
     * @return string
     */
    abstract protected function getInitialOffset(): string;

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    abstract protected function setupBackend(Backend $backend): void;

    /**
     * Retrieve a batch of IDs.
     *
     * @param Backend $backend       Search backend
     * @param string  $currentOffset String representing progress through set
     * @param int     $countPerPage  Page size
     *
     * @return array
     */
    abstract protected function getIdsFromBackend(
        Backend $backend,
        string $currentOffset,
        int $countPerPage
    ): array;
}
