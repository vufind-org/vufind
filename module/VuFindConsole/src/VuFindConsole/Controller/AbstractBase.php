<?php
/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFindConsole\Controller;
use Zend\Console\Console, Zend\Console\Getopt,
    Zend\Mvc\Controller\AbstractActionController;

/**
 * VuFind controller base class (defines some methods that can be shared by other
 * controllers).
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class AbstractBase extends AbstractActionController
{
    /**
     * Console options
     *
     * @var Getopt
     */
    protected $consoleOpts;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This controller should only be accessed from the command line!
        if (PHP_SAPI != 'cli') {
            throw new \Exception('Access denied to command line tools.');
        }

        // Get access to information about the CLI request.
        $this->consoleOpts = new Getopt([]);

        // Switch the context back to the original working directory so that
        // relative paths work as expected.  (This constant is set in
        // public/index.php)
        if (defined('ORIGINAL_WORKING_DIRECTORY')) {
            chdir(ORIGINAL_WORKING_DIRECTORY);
        }
    }

    /**
     * Warn the user if VUFIND_LOCAL_DIR is not set.
     *
     * @return void
     */
    protected function checkLocalSetting()
    {
        if (!getenv('VUFIND_LOCAL_DIR')) {
            Console::writeLine(
                "WARNING: The VUFIND_LOCAL_DIR environment variable is not set."
            );
            Console::writeLine(
                "This should point to your local configuration directory (i.e."
            );
            Console::writeLine(realpath(APPLICATION_PATH . '/local') . ").");
            Console::writeLine(
                "Without it, inappropriate default settings may be loaded."
            );
            Console::writeLine("");
        }
    }

    /**
     * Indicate failure.
     *
     * @return \Zend\Console\Response
     */
    protected function getFailureResponse()
    {
        return $this->getResponse()->setErrorLevel(1);
    }

    /**
     * Indicate success.
     *
     * @return \Zend\Console\Response
     */
    protected function getSuccessResponse()
    {
        return $this->getResponse()->setErrorLevel(0);
    }

    /**
     * Get the ILS connection.
     *
     * @return \VuFind\ILS\Connection
     */
    public function getILS()
    {
        return $this->getServiceLocator()->get('VuFind\ILSConnection');
    }

    /**
     * Get a database table object.
     *
     * @param string $table Name of table to retrieve
     *
     * @return \VuFind\Db\Table\Gateway
     */
    public function getTable($table)
    {
        return $this->getServiceLocator()->get('VuFind\DbTablePluginManager')
            ->get($table);
    }
}