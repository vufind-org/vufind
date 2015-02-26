<?php
/**
 * VuFind Sitemap
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Sitemap;
use VuFindSearch\Backend\Solr\Backend, VuFind\Search\BackendManager,
    VuFindSearch\ParamBag, Zend\Config\Config;

/**
 * Class for generating sitemaps
 *
 * @category VuFind2
 * @package  Sitemap
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
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
     * Base URL for site
     *
     * @var string
     */
    protected $baseUrl;

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
     * Mode of retrieving IDs from the index (may be 'terms' or 'search')
     *
     * @var string
     */
    protected $retrievalMode = 'terms';

    /**
     * Constructor
     *
     * @param BackendManager $bm      Search backend
     * @param string         $baseUrl VuFind base URL
     * @param Config         $config  Sitemap configuration settings
     */
    public function __construct(BackendManager $bm, $baseUrl, Config $config)
    {
        // Save incoming parameters:
        $this->backendManager = $bm;
        $this->baseUrl = $baseUrl;
        $this->config = $config;

        // Process backend configuration:
        $backendConfig = isset($this->config->Sitemap->index)
            ? $this->config->Sitemap->index : ['Solr,/Record/'];
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
     * Generate the sitemaps based on settings established by the constructor.
     *
     * @return void
     */
    public function generate()
    {
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
        $lastTerm = '';
        $count = 0;

        while (true) {
            // Get IDs and break out of the loop if we've run out:
            $ids = $this->getIdsFromBackend($backend, $lastTerm, $count);
            if (empty($ids)) {
                break;
            }

            // Write the current entry:
            $smf = $this->getNewSitemap();
            foreach ($ids as $item) {
                $loc = htmlspecialchars($recordUrl . urlencode($item));
                if (strpos($loc, 'http') === false) {
                    $loc = 'http://' . $loc;
                }
                $smf->addUrl($loc);
                $lastTerm = $item;
            }
            $filename = $this->getFilenameForPage($currentPage);
            if (false === $smf->write($filename)) {
                throw new \Exception("Problem writing $filename.");
            }

            // Update counters:
            $count += $this->countPerPage;
            $currentPage++;
        }
        return $currentPage;
    }

    /**
     * Retrieve a batch of IDs.
     *
     * @param Backend $backend  Search backend
     * @param string  $lastTerm Last term retrieved
     * @param int     $offset   Number of terms previously retrieved
     *
     * @return array
     */
    protected function getIdsFromBackend(Backend $backend, $lastTerm, $offset)
    {
        if ($this->retrievalMode == 'terms') {
            return $this->getIdsFromBackendUsingTerms($backend, $lastTerm);
        }
        return $this->getIdsFromBackendUsingSearch($backend, $offset);
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
        return null === $info ? [] : array_keys($info->toArray());
    }

    /**
     * Retrieve a batch of IDs using regular search.
     *
     * @param Backend $backend Search backend
     * @param int     $offset  Number of terms previously retrieved
     *
     * @return array
     */
    protected function getIdsFromBackendUsingSearch(Backend $backend, $offset)
    {
        $connector = $backend->getConnector();
        $key = $connector->getUniqueKey();
        $params = new ParamBag(
            [
                'q' => '*:*',
                'fl' => $key,
                'rows' => $this->countPerPage,
                'start' => $offset,
                'wt' => 'json',
                'sort' => $key . ' asc',
            ]
        );
        $raw = $connector->search($params);
        $result = json_decode($raw);
        $ids = [];
        if (isset($result->response->docs)) {
            foreach ($result->response->docs as $doc) {
                $ids[] = $doc->$key;
            }
        }
        return $ids;
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
        if (!isset($this->config->SitemapIndex->baseSitemapUrl)
            || empty($this->config->SitemapIndex->baseSitemapUrl)
        ) {
            return $this->baseUrl;
        }
        return $this->config->SitemapIndex->baseSitemapUrl;
    }
}