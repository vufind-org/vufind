<?php
/**
 * Console command: web crawler
 *
 * PHP version 7
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Solr\Writer;
use VuFind\XSLT\Importer;

/**
 * Console command: web crawler
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class WebCrawlCommand extends Command
{
    /**
     * The name of the command
     *
     * @var string
     */
    protected static $defaultName = 'import/webcrawl';

    /**
     * XSLT importer
     *
     * @var Importer
     */
    protected $importer;

    /**
     * Solr writer
     *
     * @var Writer
     */
    protected $solr;

    /**
     * Configuration from webcrawl.ini
     *
     * @var Config
     */
    protected $config;

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
        Importer $importer,
        Writer $solr,
        Config $config,
        $name = null
    ) {
        $this->importer = $importer;
        $this->solr = $solr;
        $this->config = $config;
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
            ->setDescription('Web crawler')
            ->setHelp('Crawls websites to populate VuFind\'s web index.')
            ->addOption(
                'test-only',
                null,
                InputOption::VALUE_NONE,
                'activates test mode, which displays output without updating Solr'
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
            // Only import the current sitemap if it contains URLs!
            if (isset($xml->url)) {
                try {
                    $result = $this->importer->save(
                        $file,
                        'sitemap.properties',
                        $index,
                        $testMode
                    );
                    if ($testMode) {
                        $output->writeln($result);
                    }
                } catch (\Exception $e) {
                    if ($verbose) {
                        $output->writeln(get_class($e) . ': ' . $e->getMessage());
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
        $index = $input->getOption('index');

        // Get the time we started indexing -- we'll delete records older than this
        // date after everything is finished.  Note that we subtract a few seconds
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
            $output->writeln("Error encountered during harvest.");
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
