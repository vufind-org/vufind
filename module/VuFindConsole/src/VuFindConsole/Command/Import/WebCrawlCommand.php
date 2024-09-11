<?php

/**
 * Console command: web crawler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Import;

use Laminas\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Solr\Writer;
use VuFind\XSLT\Importer;
use VuFindSearch\Backend\Solr\Document\RawXMLDocument;

use function is_string;

/**
 * Console command: web crawler
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'import/webcrawl',
    description: 'Web crawler'
)]
class WebCrawlCommand extends Command
{
    /**
     * Should we bypass cache expiration?
     *
     * @var bool
     */
    protected bool $bypassCacheExpiration = false;

    /**
     * Constructor
     *
     * @param Importer    $importer XSLT importer
     * @param Writer      $solr     Solr writer
     * @param Config      $config   Configuration from webcrawl.ini
     * @param string|null $name     The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(
        protected Importer $importer,
        protected Writer $solr,
        protected Config $config,
        $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Crawls websites to populate VuFind\'s web index.')
            ->addOption(
                'test-only',
                null,
                InputOption::VALUE_NONE,
                'activates test mode, which displays output without updating Solr'
            )->addOption(
                'use-expired-cache',
                null,
                InputOption::VALUE_NONE,
                'use cached data, even if expired; useful when the index needs to be quickly rebuilt, '
                . 'e.g. after a Solr upgrade'
            )->addOption(
                'index',
                null,
                InputOption::VALUE_OPTIONAL,
                'name of search backend to index content into',
                'SolrWeb'
            );
    }

    /**
     * Download a URL to a temporary file.
     *
     * @param string $url URL to download
     *
     * @return string     Filename of downloaded content
     */
    protected function downloadFile($url)
    {
        $file = tempnam('/tmp', 'sitemap');
        file_put_contents($file, file_get_contents($url));
        return $file;
    }

    /**
     * Remove a temporary file.
     *
     * @param string $file Name of file to delete
     *
     * @return void
     */
    protected function removeTempFile($file)
    {
        unlink($file);
    }

    /**
     * Given a URL, get the transform cache path (or null if the cache
     * is disabled).
     *
     * @param string $url URL to cache
     *
     * @return ?string
     */
    protected function getTransformCachePath(string $url): ?string
    {
        if ($dir = $this->config->Cache->transform_cache_dir ?? null) {
            return $dir . '/' . md5($url);
        }
        return null;
    }

    /**
     * Update the last_indexed dates in a cached XML document to the current
     * time so reindexing cached documents works correctly.
     *
     * @param string $xml XML to update
     *
     * @return string
     */
    protected function updateLastIndexed(string $xml): string
    {
        $newDate = date('Y-m-d\TH:i:s\Z');
        return preg_replace(
            '|<field name="last_indexed">([^<]+)</field>|',
            '<field name="last_indexed">' . $newDate . '</field>',
            $xml
        );
    }

    /**
     * Fetch transform cache data for the specified URL; return null if the cache is disabled,
     * the data is expired, or something goes wrong.
     *
     * @param OutputInterface $output  Output object
     * @param string          $url     URL of sitemap to read.
     * @param string          $lastMod Last modification date of URL.
     * @param bool            $verbose Are we in verbose mode?
     *
     * @return ?string
     */
    protected function readFromTransformCache(
        OutputInterface $output,
        string $url,
        string $lastMod,
        bool $verbose
    ): ?string {
        // If cache is write-only, don't retrieve data!
        if ($this->config->Cache->transform_cache_write_only ?? false) {
            return null;
        }
        // If we can't find the data in the cache, we can't proceed.
        if (!($path = $this->getTransformCachePath($url)) || !file_exists($path)) {
            return null;
        }
        if (strtotime($lastMod) > filemtime($path) && !$this->bypassCacheExpiration) {
            if ($verbose) {
                $output->writeln("Cached data for $url ($path) has expired.");
            }
            return null;
        }
        $rawXml = file_get_contents($path);
        if (!is_string($rawXml)) {
            $output->writeln("WARNING: Problem reading cached data for $url ($path)");
            return null;
        }
        if ($verbose) {
            $output->writeln("Found $url in cache: $path");
        }
        return $rawXml;
    }

    /**
     * Check the cache and configuration to see if the provided URL can
     * be loaded from cache, and load it to Solr if possible.
     *
     * @param OutputInterface $output   Output object
     * @param string          $url      URL of sitemap to read.
     * @param string          $lastMod  Last modification date of URL.
     * @param bool            $verbose  Are we in verbose mode?
     * @param string          $index    Solr index to update
     * @param bool            $testMode Are we in test mode?
     *
     * @return bool           True if loaded from cache, false if not.
     */
    protected function indexFromTransformCache(
        OutputInterface $output,
        string $url,
        string $lastMod,
        bool $verbose = false,
        string $index = 'SolrWeb',
        bool $testMode = false
    ): bool {
        $rawXml = $this->readFromTransformCache($output, $url, $lastMod, $verbose);
        if ($rawXml === null) {
            return false;
        }
        $xml = $this->updateLastIndexed($rawXml);
        if ($testMode) {
            $output->writeln($xml);
        } else {
            $this->solr->save($index, new RawXMLDocument($xml));
        }
        return true;
    }

    /**
     * Update the transform cache (if activated). Returns true if the cache was updated,
     * false otherwise.
     *
     * @param string $url    URL to use for cache key
     * @param string $result Result of transforming the URL
     *
     * @return bool
     */
    protected function updateTransformCache(string $url, string $result): bool
    {
        if ($transformCachePath = $this->getTransformCachePath($url)) {
            return false !== file_put_contents($transformCachePath, $result);
        }
        return false;
    }

    /**
     * Process a sitemap URL, either harvesting its contents directly or recursively
     * reading in child sitemaps.
     *
     * @param OutputInterface $output   Output object
     * @param string          $url      URL of sitemap to read.
     * @param bool            $verbose  Are we in verbose mode?
     * @param string          $index    Solr index to update
     * @param bool            $testMode Are we in test mode?
     *
     * @return bool           True on success, false on error.
     */
    protected function harvestSitemap(
        OutputInterface $output,
        $url,
        $verbose = false,
        $index = 'SolrWeb',
        $testMode = false
    ) {
        // Date to use as a default "last modification" date in scenarios where we
        // don't care about cache invalidation.
        $pastDate = '1980-01-01';

        // If we're not concerned about cache expiration, we can potentially
        // short-circuit the process with the cache up front. Otherwise, we'll
        // need to wait until we can get last modification dates to know whether
        // it's safe to rely on cached data.
        if (
            $this->bypassCacheExpiration
            && $this->indexFromTransformCache($output, $url, $pastDate, $verbose, $index, $testMode)
        ) {
            return true;
        }

        if ($verbose) {
            $output->writeln("Harvesting $url...");
        }

        $retVal = true;

        $file = $this->downloadFile($url);
        $xml = simplexml_load_file($file);
        if ($xml) {
            // Are there any child sitemaps?  If so, pull them in:
            $results = $xml->sitemap ?? [];
            foreach ($results as $current) {
                if (isset($current->loc)) {
                    // If there's a last modification date (or we're forcing a
                    // reindex from the cache) and we can retrieve data from the
                    // cache, we can bypass the harvest.
                    if (
                        (!isset($current->lastmod) && !$this->bypassCacheExpiration)
                        || !$this->indexFromTransformCache(
                            $output,
                            (string)$current->loc,
                            (string)($current->lastmod ?? $pastDate),
                            $verbose,
                            $index,
                            $testMode
                        )
                    ) {
                        $success = $this->harvestSitemap(
                            $output,
                            (string)$current->loc,
                            $verbose,
                            $index,
                            $testMode
                        );
                        if (!$success) {
                            $retVal = false;
                        }
                    }
                }
            }
            // Only import the current sitemap if it contains URLs!
            if (isset($xml->url)) {
                try {
                    $result = $this->importer->save(
                        $file,
                        'sitemap.properties',
                        $index,
                        $testMode
                    );
                    if ($result && $this->updateTransformCache($url, $result) && $verbose) {
                        $output->writeln('Wrote results to transform cache.');
                    }
                    if ($testMode) {
                        $output->writeln($result);
                    }
                } catch (\Exception $e) {
                    if ($verbose) {
                        $output->writeln($e::class . ': ' . $e->getMessage());
                    }
                    $retVal = false;
                }
            }
        }
        $this->removeTempFile($file);
        return $retVal;
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get command line parameters:
        $testMode = $input->getOption('test-only') ? true : false;
        $this->bypassCacheExpiration = $input->getOption('use-expired-cache') ? true : false;
        $index = $input->getOption('index');

        // Get the time we started indexing -- we'll delete records older than this
        // date after everything is finished. Note that we subtract a few seconds
        // for safety.
        $startTime = date('Y-m-d\TH:i:s\Z', time() - 5);

        // Are we in verbose mode?
        $verbose = ($this->config->General->verbose ?? false)
            || ($input->hasOption('verbose') && $input->getOption('verbose'));

        // Loop through sitemap URLs in the config file.
        $error = false;
        foreach ($this->config->Sitemaps->url as $current) {
            $error = $error || !$this->harvestSitemap(
                $output,
                $current,
                $verbose,
                $index,
                $testMode
            );
        }
        if ($error) {
            $output->writeln('Error encountered during harvest.');
        }

        // Skip Solr operations if we're in test mode.
        if (!$testMode) {
            if ($verbose) {
                $output->writeln("Deleting old records (prior to $startTime)...");
            }
            // Perform the delete of outdated records:
            $this->solr
                ->deleteByQuery($index, 'last_indexed:[* TO ' . $startTime . ']');
            if ($verbose) {
                $output->writeln('Committing...');
            }
            $this->solr->commit($index);
            if ($verbose) {
                $output->writeln('Optimizing...');
            }
            $this->solr->optimize($index);
        }
        return $error ? 1 : 0;
    }
}
