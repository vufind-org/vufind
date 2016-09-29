<?php
/**
 * VuFind Stastics Controller
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
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Statistics;
use VuFind\Statistics\Driver\PluginManager, Zend\Config\Config;

/**
 * VuFind Search Controller
 *
 * @category VuFind
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractBase
{
    /**
     * Array of statistics drivers (null if not yet initialized)
     *
     * @var array
     */
    protected $drivers = null;

    /**
     * An identifier of the source of the current set of statistics
     *
     * @var string
     */
    protected $source;

    /**
     * Session ID
     *
     * @var string
     */
    protected $sessId;

    /**
     * Statistics driver plugin manager
     *
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config        $config VuFind configuration
     * @param PluginManager $pm     Statistics driver plugin manager
     * @param string        $sessId Session ID
     */
    public function __construct(Config $config, PluginManager $pm, $sessId)
    {
        $this->config = $config;
        // Source pulled from class name
        $source = explode('\\', get_class($this));
        $this->source = end($source);
        $this->pluginManager = $pm;
        $this->sessId = $sessId;
    }
    
    /**
     * Get the stat drivers.
     *
     * @return array
     */
    protected function getDrivers()
    {
        if (null === $this->drivers) {
            $this->drivers = $this->getDriversForSource($this->source);
        }
        return $this->drivers;
    }

    /**
     * Create an array of statistics drivers that log a particular type of data.
     *
     * @param string $source Name of data type, or null to only obtain objects which
     * log ALL data types.
     * @param bool   $getAll If set to true, this parameter causes the method to
     * ignore $source and return every driver object.  This should only be used
     * when reading data, never when writing.
     *
     * @return array
     */
    public function getDriversForSource($source, $getAll = false)
    {
        $drivers = [];

        // For each mode
        if (isset($this->config->Statistics->mode)) {
            foreach ($this->config->Statistics->mode as $config) {
                $setting = explode(':', $config);

                // If the config setting has a limiter, we may need to skip this
                // record, so we should do some checks (unless we're set to accept
                // any match through the $getAll parameter).
                if (count($setting) > 1 && !$getAll) {
                    // If we only want global drivers, we don't want anything with
                    // limits.
                    if (null === $source) {
                        continue;
                    }

                    // If we got this far, we know that $source is not null; let's
                    // see if the requested source is supported.
                    $legalOptions = array_map('trim', explode(',', $setting[1]));
                    if (!in_array($source, $legalOptions)) {
                        continue;
                    }
                }

                // If we got this far, we want the current option!  Build the driver:
                $newDriver = $this->pluginManager->get($setting[0]);

                // Set the name of the data source;  we use the special value
                // "global" to represent global writer requests (the special null
                // case):
                $newDriver->setSource(null === $source ? 'global' : $source);
                $drivers[] = $newDriver;
            }
        }

        return $drivers;
    }

    /**
     * Chooses the appropriate saving actions and formats based on config
     *
     * @param array                        $data    Associative array of data
     * @param Zend_Controller_Request_Http $request Request data from the controller
     *
     * @return void
     */
    protected function save($data, $request)
    {
        $drivers = $this->getDrivers();
        if (!empty($drivers)) {
            $userData = $this->getUserData($request);
            foreach ($drivers as $writer) {
                $writer->write($data, $userData);
            }
        }
    }

    /**
     * Returns a count and most used list
     *
     * @param int  $listLength How long the top list is
     * @param bool $bySource   Sort data by source?
     *
     * @return mixed
     */
    abstract public function getStatsSummary($listLength, $bySource);

    /**
     * Returns the common information available without data
     *
     * @param Zend_Controller_Request_Http $request Request data from the controller
     *
     * @return array commonData
     */
    protected function getUserData($request)
    {
        $server = $request->getServer();
        $agent = $server->get('HTTP_USER_AGENT');
        $parts = explode(' ', $this->getBrowser($agent));
        $browser = $parts[0];
        $version = isset($parts[1]) ? $parts[1] : '';
        return [
            'id'               => uniqid('', true),
            'datestamp'        => substr(date('c', strtotime('now')), 0, -6) . 'Z',
            'browser'          => $browser,
            'browserVersion'   => $version,
            'ipaddress'        => $server->get('REMOTE_ADDR'),
            'referrer'         => ($server->get('HTTP_REFERER') == null)
                ? 'Manual'
                : $server->get('HTTP_REFERER'),
            'url'              => $server->get('REQUEST_URI'),
            'session'          => $this->sessId
        ];
    }

    /**
     * Parse the browser name and version from the agent string
     *
     * @param string $agent Browser user agent string
     *
     * @return string browser name and version
     */
    public function getBrowser($agent)
    {
        // Try to use browscap.ini if available:
        $browser = @get_browser($agent, true);
        if (isset($browser['parent'])) {
            return $browser['parent'];
        }

        // If browscap.ini didn't work, do our best:
        if (strpos($agent, "Opera") > -1) {
            $split = explode(' ', $agent);
            return str_replace('/', ' ', $split[0]);
        }
        if (strpos($agent, "Chrome") > -1) {
            $split = explode(' ', $agent);
            return str_replace('/', ' ', $split[count($split) - 2]);
        }
        if (strpos($agent, "Firefox") > -1 || strpos($agent, "Safari") > -1) {
            $split = explode(' ', $agent);
            return str_replace('/', ' ', end($split));
        }
        if (strpos($agent, "compatible;") > -1) {
            $data = explode("compatible;", $agent);
            $split = preg_split('/[;\)]/', $data[1]);
            return str_replace('/', ' ', trim($split[0]));
        }
    }
}
