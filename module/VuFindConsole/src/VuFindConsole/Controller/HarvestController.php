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
use VuFindHarvest\OaiPmh\HarvesterConsoleRunner, Zend\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class HarvestController extends AbstractBase
{
    /**
     * Get the base directory for harvesting OAI-PMH data.
     *
     * @return string
     */
    protected function getHarvestRoot()
    {
        // Get the base VuFind path:
        if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            $home = LOCAL_OVERRIDE_DIR;
        } else {
            $home = realpath(APPLICATION_PATH . '/..');
        }

        // Build the full harvest path:
        $dir = $home . '/harvest/';

        // Create the directory if it does not already exist:
        if (!is_dir($dir) && !mkdir($dir)) {
            throw new \Exception("Problem creating directory {$dir}.");
        }

        return $dir;
    }

    /**
     * Harvest OAI-PMH records.
     *
     * @return \Zend\Console\Response
     */
    public function harvestoaiAction()
    {
        $this->checkLocalSetting();

        // Get default options, add the default --ini setting if missing:
        $opts = HarvesterConsoleRunner::getDefaultOptions();
        if (!$opts->getOption('ini')) {
            $ini = \VuFind\Config\Locator::getConfigPath('oai.ini', 'harvest');
            $opts->addArguments(['--ini=' . $ini]);
        }

        // Get the default VuFind HTTP client:
        $client = $this->getServiceLocator()->get('VuFind\Http')->createClient();

        // Run the job!
        $runner = new HarvesterConsoleRunner(
            $opts, $client, $this->getHarvestRoot()
        );
        return $runner->run()
            ? $this->getSuccessResponse() : $this->getFailureResponse();
    }

    /**
     * Merge harvested MARC records into a single <collection>
     *
     * @return \Zend\Console\Response
     * @author Thomas Schwaerzler <thomas.schwaerzler@uibk.ac.at>
     */
    public function mergemarcAction()
    {
        $this->checkLocalSetting();

        $dir = rtrim($this->getRequest()->getParam('dir', ''), '/');
        if (empty($dir)) {
            $scriptName = $this->getRequest()->getScriptName();
            if (substr($scriptName, -9) === 'index.php') {
                $scriptName .= ' harvest merge-marc';
            }
            Console::writeLine('Merge MARC XML files into a single <collection>;');
            Console::writeLine('writes to stdout.');
            Console::writeLine('');
            Console::writeLine('Usage: ' . $scriptName . ' <path_to_directory>');
            Console::writeLine(
                '<path_to_directory>: a directory containing MARC XML files to merge'
            );
            return $this->getFailureResponse();
        }

        if (!($handle = opendir($dir))) {
            Console::writeLine("Cannot open directory: {$dir}");
            return $this->getFailureResponse();
        }

        Console::writeLine('<collection>');
        while (false !== ($file = readdir($handle))) {
            // Only operate on XML files:
            if (pathinfo($file, PATHINFO_EXTENSION) === "xml") {
                // get file content
                $filePath = $dir . '/' . $file;
                $fileContent = file_get_contents($filePath);

                // output content:
                Console::writeLine("<!-- $filePath -->");
                Console::write($fileContent);
            }
        }
        Console::writeLine('</collection>');
    }
}
