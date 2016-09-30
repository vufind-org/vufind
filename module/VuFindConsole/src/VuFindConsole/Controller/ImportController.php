<?php
/**
 * CLI Controller Module
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;
use VuFind\XSLT\Importer, Zend\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ImportController extends AbstractBase
{
    /**
     * XSLT Import Tool
     *
     * @return \Zend\Console\Response
     */
    public function importXslAction()
    {
        // Parse switches:
        $this->consoleOpts->addRules(
            ['test-only' => 'Use test mode', 'index-s' => 'Solr index to use']
        );
        $testMode = $this->consoleOpts->getOption('test-only') ? true : false;
        $index = $this->consoleOpts->getOption('index');
        if (empty($index)) {
            $index = 'Solr';
        }

        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            Console::writeLine(
                "Usage: import-xsl.php [--test-only] [--index <type>] "
                . "XML_file properties_file"
            );
            Console::writeLine("\tXML_file - source file to index");
            Console::writeLine("\tproperties_file - import configuration file");
            Console::writeLine(
                "If the optional --test-only flag is set, "
                . "transformed XML will be displayed"
            );
            Console::writeLine(
                "on screen for debugging purposes, "
                . "but it will not be indexed into VuFind."
            );
            Console::writeLine("");
            Console::writeLine(
                "If the optional --index parameter is set, "
                . "it must be followed by the name of"
            );
            Console::writeLine(
                "a class for accessing Solr; it defaults to the "
                . "standard Solr class, but could"
            );
            Console::writeLine(
                "be overridden with, for example, SolrAuth to "
                . "load authority records."
            );
            Console::writeLine("");
            Console::writeLine(
                "Note: See ojs.properties for configuration examples."
            );
            return $this->getFailureResponse();
        }

        // Try to import the document if successful:
        try {
            $this->performImport($argv[0], $argv[1], $index, $testMode);
        } catch (\Exception $e) {
            Console::writeLine("Fatal error: " . $e->getMessage());
            if (is_callable([$e, 'getPrevious']) && $e = $e->getPrevious()) {
                while ($e) {
                    Console::writeLine("Previous exception: " . $e->getMessage());
                    $e = $e->getPrevious();
                }
            }
            return $this->getFailureResponse();
        }
        if (!$testMode) {
            Console::writeLine("Successfully imported {$argv[0]}...");
        }
        return $this->getSuccessResponse();
    }

    /**
     * Support method -- perform an XML import.
     *
     * @param string $xml        XML file to load
     * @param string $properties Configuration file to load
     * @param string $index      Name of backend to write to
     * @param bool   $testMode   Use test mode?
     *
     * @return void
     */
    protected function performImport($xml, $properties, $index = 'Solr',
        $testMode = false
    ) {
        $importer = new Importer();
        $importer->setServiceLocator($this->getServiceLocator());
        $importer->save($xml, $properties, $index, $testMode);
    }

    /**
     * Tool to crawl website for special index.
     *
     * @return \Zend\Console\Response
     */
    public function webcrawlAction()
    {
        // Parse switches:
        $this->consoleOpts->addRules(
            ['test-only' => 'Use test mode', 'index-s' => 'Solr index to use']
        );
        $testMode = $this->consoleOpts->getOption('test-only') ? true : false;
        $index = $this->consoleOpts->getOption('index');
        if (empty($index)) {
            $index = 'SolrWeb';
        }

        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
        $crawlConfig = $configLoader->get('webcrawl');

        // Get the time we started indexing -- we'll delete records older than this
        // date after everything is finished.  Note that we subtract a few seconds
        // for safety.
        $startTime = date('Y-m-d\TH:i:s\Z', time() - 5);

        // Are we in verbose mode?
        $verbose = isset($crawlConfig->General->verbose)
            && $crawlConfig->General->verbose;

        // Loop through sitemap URLs in the config file.
        foreach ($crawlConfig->Sitemaps->url as $current) {
            $this->harvestSitemap($current, $verbose, $index, $testMode);
        }

        // Skip Solr operations if we're in test mode.
        if (!$testMode) {
            $solr = $this->getServiceLocator()->get('VuFind\Solr\Writer');
            if ($verbose) {
                Console::writeLine("Deleting old records (prior to $startTime)...");
            }
            // Perform the delete of outdated records:
            $solr->deleteByQuery($index, 'last_indexed:[* TO ' . $startTime . ']');
            if ($verbose) {
                Console::writeLine('Committing...');
            }
            $solr->commit($index);
            if ($verbose) {
                Console::writeLine('Optimizing...');
            }
            $solr->optimize($index);
        }
    }

    /**
     * Support method for webcrawlAction().
     *
     * Process a sitemap URL, either harvesting its contents directly or recursively
     * reading in child sitemaps.
     *
     * @param string $url      URL of sitemap to read.
     * @param bool   $verbose  Are we in verbose mode?
     * @param string $index    Solr index to update
     * @param bool   $testMode Are we in test mode?
     *
     * @return bool       True on success, false on error.
     */
    protected function harvestSitemap($url, $verbose = false, $index = 'SolrWeb',
        $testMode = false
    ) {
        if ($verbose) {
            Console::writeLine("Harvesting $url...");
        }

        $retVal = true;

        $file = tempnam('/tmp', 'sitemap');
        file_put_contents($file, file_get_contents($url));
        $xml = simplexml_load_file($file);
        if ($xml) {
            // Are there any child sitemaps?  If so, pull them in:
            $results = isset($xml->sitemap) ? $xml->sitemap : [];
            foreach ($results as $current) {
                if (isset($current->loc)) {
                    $success = $this->harvestSitemap(
                        (string)$current->loc, $verbose, $index, $testMode
                    );
                    if (!$success) {
                        $retVal = false;
                    }
                }
            }
            // Only import the current sitemap if it contains URLs!
            if (isset($xml->url)) {
                try {
                    $this->performImport(
                        $file, 'sitemap.properties', $index, $testMode
                    );
                } catch (\Exception $e) {
                    if ($verbose) {
                        Console::writeLine(get_class($e) . ': ' . $e->getMessage());
                    }
                    $retVal = false;
                }
            }
        }
        unlink($file);
        return $retVal;
    }
}
