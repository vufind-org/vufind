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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFindConsole\Controller;
use VuFind\Config\Reader as ConfigReader, VuFind\Harvester\NAF, VuFind\Harvester\OAI,
    Zend\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class HarvestController extends AbstractBase
{
    /**
     * Harvest the LC Name Authority File.
     *
     * @return void
     */
    public function harvestnafAction()
    {
        $this->checkLocalSetting();

        // Perform the harvest. Note that first command line parameter
        // may be used to start at a particular date.
        try {
            $harvest = new NAF();
            $argv = $this->consoleOpts->getRemainingArgs();
            if (isset($argv[0])) {
                $harvest->setStartDate($argv[0]);
            }
            $harvest->launch();
        } catch (\Exception $e) {
            Console::writeLine($e->getMessage());
            return $this->getFailureResponse();
        }
        return $this->getSuccessResponse();
    }

    /**
     * Harvest OAI-PMH records.
     *
     * @return void
     */
    public function harvestoaiAction()
    {
        $this->checkLocalSetting();

        // Read Config files
        $configFile = ConfigReader::getConfigPath('oai.ini', 'harvest');
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
                $oaiSettings = array($argv[0] => $oaiSettings[$argv[0]]);
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
                    $harvest = new OAI($target, $settings);
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
}
