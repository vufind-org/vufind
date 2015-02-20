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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFindConsole\Controller;
use VuFind\Harvester\OAI, Zend\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class HarvestController extends AbstractBase
{
    /**
     * Harvest OAI-PMH records.
     *
     * @return \Zend\Console\Response
     */
    public function harvestoaiAction()
    {
        $this->checkLocalSetting();

        // Parse switches:
        $this->consoleOpts->addRules(
            ['from-s' => 'Harvest start date', 'until-s' => 'Harvest end date']
        );
        $from = $this->consoleOpts->getOption('from');
        $until = $this->consoleOpts->getOption('until');

        // Read Config files
        $configFile = \VuFind\Config\Locator::getConfigPath('oai.ini', 'harvest');
        $oaiSettings = @parse_ini_file($configFile, true);
        if (empty($oaiSettings)) {
            Console::writeLine("Please add OAI-PMH settings to oai.ini.");
            return $this->getFailureResponse();
        }

        // If first command line parameter is set, see if we can limit to just the
        // specified OAI harvester:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (isset($argv[0])) {
            if (isset($oaiSettings[$argv[0]])) {
                $oaiSettings = [$argv[0] => $oaiSettings[$argv[0]]];
            } else {
                Console::writeLine("Could not load settings for {$argv[0]}.");
                return $this->getFailureResponse();
            }
        }

        // Loop through all the settings and perform harvests:
        $processed = 0;
        foreach ($oaiSettings as $target => $settings) {
            if (!empty($target) && !empty($settings)) {
                Console::writeLine("Processing {$target}...");
                try {
                    $client = $this->getServiceLocator()->get('VuFind\Http')
                        ->createClient();
                    $harvest = new OAI($target, $settings, $client, $from, $until);
                    $harvest->launch();
                } catch (\Exception $e) {
                    Console::writeLine($e->getMessage());
                    return $this->getFailureResponse();
                }
                $processed++;
            }
        }

        // All done.
        Console::writeLine(
            "Completed without errors -- {$processed} source(s) processed."
        );
        return $this->getSuccessResponse();
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

        $argv = $this->consoleOpts->getRemainingArgs();
        $dir = isset($argv[0]) ? rtrim($argv[0], '/') : '';
        if (empty($dir)) {
            $scriptName = $this->getRequest()->getScriptName();
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
