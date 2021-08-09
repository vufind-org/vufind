<?php
/**
 * VuFind Sitemap
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Sitemap;

use Laminas\Config\Config;
use VuFind\Search\BackendManager;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindSearch\Service as SearchService;

/**
 * Class for generating sitemaps
 *
 * @category VuFind
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Generator
{
    /**
     * Search backend manager.
     *
     * @var BackendManager
     */
    protected $backendManager;

    /**
     * Search service.
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Base URL for site
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Base URL for sitemap
     *
     * @var string
     */
    protected $baseSitemapUrl;

    /**
     * Settings specifying which backends to index.
     *
     * @var array
     */
    protected $backendSettings;

    /**
     * Sitemap configuration (sitemap.ini)
     *
     * @var Config
     */
    protected $config;

    /**
     * Frequency of URL updates (always, daily, weekly, monthly, yearly, never)
     *
     * @var string
     */
    protected $frequency;

    /**
     * URL entries per sitemap
     *
     * @var int
     */
    protected $countPerPage;

    /**
     * Base path to sitemap files, including base filename
     *
     * @var string
     */
    protected $fileStart;

    /**
     * Filename of sitemap index
     *
     * @var string
     */
    protected $indexFile = false;

    /**
     * Warnings thrown during sitemap generation
     *
     * @var array
     */
    protected $warnings = [];

    /**
     * Verbose callback
     *
     * @var \Callable
     */
    protected $verbose = null;

    /**
     * Mode of retrieving IDs from the index (may be 'terms' or 'search')
     *
     * @var string
     */
    protected $retrievalMode = 'search';

    /**
     * Constructor
     *
     * @param BackendManager $bm      Search backend manaver
     * @param SearchService  $ss      Search manager
     * @param string         $baseUrl VuFind base URL
     * @param Config         $config  Sitemap configuration settings
     */
    public function __construct(BackendManager $bm, SearchService $ss, $baseUrl,
        Config $config
    ) {
        // Save incoming parameters:
        $this->backendManager = $bm;
        $this->searchService = $ss;
        $this->baseUrl = $baseUrl;
        $this->config = $config;
        $this->baseSitemapUrl = empty($this->config->SitemapIndex->baseSitemapUrl)
            ? $this->baseUrl : $this->config->SitemapIndex->baseSitemapUrl;

        // Process backend configuration:
        $backendConfig = $this->config->Sitemap->index ?? ['Solr,/Record/'];
        $backendConfig = is_callable([$backendConfig, 'toArray'])
            ? $backendConfig->toArray() : (array)$backendConfig;
        $callback = function ($n) {
            $parts = array_map('trim', explode(',', $n));
            return ['id' => $parts[0], 'url' => $parts[1]];
        };
        $this->backendSettings = array_map($callback, $backendConfig);

        // Store other key config settings:
        $this->frequency = $this->config->Sitemap->frequency;
        $this->countPerPage = $this->config->Sitemap->countPerPage;
        $this->fileStart = $this->config->Sitemap->fileLocation . '/' .
            $this->config->Sitemap->fileName;
        if (isset($this->config->Sitemap->retrievalMode)) {
            $this->retrievalMode = $this->config->Sitemap->retrievalMode;
        }
        if (isset($this->config->SitemapIndex->indexFileName)) {
            $this->indexFile = $this->config->Sitemap->fileLocation . '/' .
                $this->config->SitemapIndex->indexFileName . '.xml';
        }
    }

    /**
     * Get/set verbose callback
     *
     * @param \Callable|null $newMode Callback for writing verbose messages (or null
     * to disable them)
     *
     * @return \Callable|null Current verbose callback (null if disabled)
     */
    public function setVerbose($newMode = null)
    {
        if (null !== $newMode) {
            $this->verbose = $newMode;
        }
        return $this->verbose;
    }

    /**
     * Write a verbose message (if configured to do so)
     *
     * @param string $msg Message to display
     *
     * @return void
     */
    protected function verboseMsg($msg)
    {
        if (is_callable($this->verbose)) {
            call_user_func($this->verbose, $msg);
        }
    }

    /**
     * Get/set base url
     *
     * @param string $newUrl New base url
     *
     * @return string Current or new base url
     */
    public function setBaseUrl($newUrl = null)
    {
        if (null !== $newUrl) {
            $this->baseUrl = $newUrl;
        }
        return $this->baseUrl;
    }

    /**
     * Get/set base sitemap url
     *
     * @param string $newUrl New base sitemap url
     *
     * @return string Current or new base sitemap url
     */
    public function setBaseSitemapUrl($newUrl = null)
    {
        if (null !== $newUrl) {
            $this->baseSitemapUrl = $newUrl;
        }
        return $this->baseSitemapUrl;
    }

    /**
     * Get the current microtime, formatted to a number.
     *
     * @return float
     */
    protected function getTime()
    {
        $time = explode(" ", microtime());
        return $time[1] + $time[0];
    }

    /**
     * Generate the sitemaps based on settings established by the constructor.
     *
     * @return void
     */
    public function generate()
    {
        // Start timer:
        $startTime = $this->getTime();

        // Initialize variable
        $currentPage = 1;

        // Loop through all backends
        foreach ($this->backendSettings as $current) {
            $backend = $this->backendManager->get($current['id']);
            if (!($backend instanceof Backend)) {
                throw new \Exception('Unsupported backend: ' . get_class($backend));
            }
            $recordUrl = $this->baseUrl . $current['url'];
            $currentPage = $this
                ->generateForBackend($backend, $recordUrl, $currentPage);
        }

        // Set-up Sitemap Index
        $this->buildIndex($currentPage - 1);

        // Display total elapsed time in verbose mode:
        $this->verboseMsg(
            'Elapsed time (in seconds): ' . round($this->getTime() - $startTime)
        );
    }

    /**
     * Get array of warning messages thrown during build.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Generate sitemap files for a single search backend.
     *
     * @param Backend $backend     Search backend
     * @param string  $recordUrl   Base URL for record links
     * @param int     $currentPage Sitemap page number to start generating
     *
     * @return int                 Next sitemap page number to generate
     */
    protected function generateForBackend(Backend $backend, $recordUrl, $currentPage)
    {
        // Starting offset varies depending on retrieval mode:
        $currentOffset = ($this->retrievalMode === 'terms') ? '' : '*';
        $recordCount = 0;

        $this->setupBackend($backend);

        while (true) {
            // Get IDs and break out of the loop if we've run out:
            $result = $this->getIdsFromBackend($backend, $currentOffset);
            if (empty($result['ids'])) {
                break;
            }
            $currentOffset = $result['nextOffset'];

            // Write the current entry:
            $smf = $this->getNewSitemap();
            foreach ($result['ids'] as $item) {
                $loc = htmlspecialchars($recordUrl . urlencode($item));
                if (strpos($loc, 'http') === false) {
                    $loc = 'http://' . $loc;
                }
                $smf->addUrl($loc);
            }
            $filename = $this->getFilenameForPage($currentPage);
            if (false === $smf->write($filename)) {
                throw new \Exception("Problem writing $filename.");
            }

            // Update total record count:
            $recordCount += count($result['ids']);

            $this->verboseMsg("Page $currentPage, $recordCount processed");

            // Update counter:
            $currentPage++;
        }
        return $currentPage;
    }

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    protected function setupBackend(Backend $backend)
    {
        $method = $this->retrievalMode == 'terms'
            ? 'setupBackendUsingTerms' : 'setupBackendUsingCursorMark';
        return $this->$method($backend);
    }

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    protected function setupBackendUsingTerms(Backend $backend)
    {
    }

    /**
     * Set up the backend.
     *
     * @param Backend $backend Search backend
     *
     * @return void
     */
    protected function setupBackendUsingCursorMark(Backend $backend)
    {
        // Set up the record factory. We use a very simple factory since performance
        // is important and we only need the identifier.
        $recordFactory = function ($data) {
            return new \VuFindSearch\Response\SimpleRecord($data);
        };
        $collectionFactory = new RecordCollectionFactory($recordFactory);
        $backend->setRecordCollectionFactory($collectionFactory);
    }

    /**
     * Retrieve a batch of IDs.
     *
     * @param Backend $backend       Search backend
     * @param string  $currentOffset String representing progress through set
     *
     * @return array
     */
    protected function getIdsFromBackend(Backend $backend, $currentOffset)
    {
        $method = $this->retrievalMode == 'terms'
            ? 'getIdsFromBackendUsingTerms' : 'getIdsFromBackendUsingCursorMark';
        return $this->$method($backend, $currentOffset);
    }

    /**
     * Retrieve a batch of IDs using the terms component.
     *
     * @param Backend $backend  Search backend
     * @param string  $lastTerm Last term retrieved
     *
     * @return array
     */
    protected function getIdsFromBackendUsingTerms(Backend $backend, $lastTerm)
    {
        $key = $backend->getConnector()->getUniqueKey();
        $info = $backend->terms($key, $lastTerm, $this->countPerPage)
            ->getFieldTerms($key);
        $ids = null === $info ? [] : array_keys($info->toArray());
        $nextOffset = empty($ids) ? null : $ids[count($ids) - 1];
        return compact('ids', 'nextOffset');
    }

    /**
     * Retrieve a batch of IDs using a cursorMark.
     *
     * @param Backend $backend    Search backend
     * @param string  $cursorMark cursorMark
     *
     * @return array
     */
    protected function getIdsFromBackendUsingCursorMark(Backend $backend, $cursorMark
    ) {
        // If the previous cursor mark matches the current one, we're finished!
        static $prevCursorMark = '';
        if ($cursorMark === $prevCursorMark) {
            return ['ids' => [], 'cursorMark' => $cursorMark];
        }
        $prevCursorMark = $cursorMark;

        $connector = $backend->getConnector();
        $key = $connector->getUniqueKey();
        $params = new ParamBag(
            [
                'q' => '*:*',
                'rows' => $this->countPerPage,
                'start' => 0, // Always 0 when using a cursorMark
                'wt' => 'json',
                'sort' => $key . ' asc',
                // Override any default timeAllowed since it cannot be used with
                // cursorMark
                'timeAllowed' => -1,
                'cursorMark' => $cursorMark
            ]
        );
        $results = $this->searchService->getIds(
            $backend->getIdentifier(),
            new Query('*:*'),
            0,
            $this->countPerPage,
            $params
        );
        $ids = [];
        foreach ($results->getRecords() as $doc) {
            $ids[] = $doc->get($key);
        }
        $nextOffset = $results->getCursorMark();
        return compact('ids', 'nextOffset');
    }

    /**
     * Write a sitemap index if requested.
     *
     * @param int $totalPages Total number of sitemap pages generated.
     *
     * @return void
     */
    protected function buildIndex($totalPages)
    {
        // Only build index file if requested:
        if ($this->indexFile !== false) {
            $smf = $this->getNewSitemapIndex();
            $baseUrl = $this->getBaseSitemapIndexUrl();

            // Add a <sitemap /> group for a static sitemap file.
            // See sitemap.ini for more information on this option.
            if (isset($this->config->SitemapIndex->baseSitemapFileName)) {
                $baseSitemapFile = $this->config->Sitemap->fileLocation . '/' .
                    $this->config->SitemapIndex->baseSitemapFileName . '.xml';
                // Only add the <sitemap /> group if the file exists
                // in the directory where the other sitemap files
                // are saved, i.e. ['Sitemap']['fileLocation']
                if (file_exists($baseSitemapFile)) {
                    $file = "{$this->config->SitemapIndex->baseSitemapFileName}.xml";
                    $smf->addUrl($baseUrl . '/' . $file);
                } else {
                    $this->warnings[] = "WARNING: Can't open file "
                        . $baseSitemapFile . '. '
                        . 'The sitemap index will be generated '
                        . 'without this sitemap file.';
                }
            }

            // Add <sitemap /> group for each sitemap file generated.
            for ($i = 1; $i <= $totalPages; $i++) {
                $sitemapNumber = ($i == 1) ? "" : "-" . $i;
                $file = $this->config->Sitemap->fileName . $sitemapNumber . '.xml';
                $smf->addUrl($baseUrl . '/' . $file);
            }

            if (false === $smf->write($this->indexFile)) {
                throw new \Exception("Problem writing $this->indexFile.");
            }
        }
    }

    /**
     * Get a fresh SitemapIndex object.
     *
     * @return IndexWriter
     */
    protected function getNewSitemapIndex()
    {
        return new SitemapIndex();
    }

    /**
     * Get a fresh Sitemap object.
     *
     * @return SitemapWriter
     */
    protected function getNewSitemap()
    {
        return new Sitemap($this->frequency);
    }

    /**
     * Get the filename for the specified page number.
     *
     * @param int $page Page number
     *
     * @return string
     */
    protected function getFilenameForPage($page)
    {
        return $this->fileStart . ($page == 1 ? '' : '-' . $page) . '.xml';
    }

    /**
     * Get the base URL for sitemap index files
     *
     * @return string
     */
    protected function getBaseSitemapIndexUrl()
    {
        // Pick the appropriate base URL based on the configuration files:
        return $this->baseSitemapUrl;
    }
}
