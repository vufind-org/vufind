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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Statistics;
use VuFind\Config\Reader as ConfigReader;

/**
 * VuFind Search Controller
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
abstract class AbstractBase
{
    protected $drivers;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Source pulled from class name
        $source = explode('_', get_class($this));
        $source = end($source);
        $this->drivers = self::getDriversForSource($source);
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
    public static function getDriversForSource($source, $getAll = false)
    {
        // Load configurations
        $configs = ConfigReader::getConfig();

        $drivers = array();

        // For each mode
        if (isset($configs->Statistics->mode)) {
            foreach ($configs->Statistics->mode as $config) {
                $setting = explode(':', $config);

                // If the config setting has a limiter, we may need to skip this
                // record, so we should do some checks (unless we're set to accept
                // any match through the $getAll parameter).
                if (count($setting) > 1 && !$getAll) {
                    // If we only want global drivers, we don't want anything with
                    // limits.
                    if (is_null($source)) {
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
                $class = 'VuFind\Statistics\Driver\\' . ucwords($setting[0]);

                // When we construct the driver, we pass the name of the data source;
                // we use the special value "global" to represent global writer
                // requests (the special null case):
                $drivers[] = new $class(is_null($source) ? 'global' : $source);
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
        if (!empty($this->drivers)) {
            $userData = $this->getUserData($request);
            foreach ($this->drivers as $writer) {
                $writer->write($data, $userData);
            }
        }
    }

    /**
     * Returns a count and most used list
     *
     * @param Configuration $drivers    Active drivers to use as sources
     * to use to find where the data is being saved
     * @param integer       $listLength Length of the top list
     *
     * @return mixed
     */
    abstract public function getStatsSummary($drivers, $listLength);

    /**
     * Returns the common information available without data
     *
     * @param Zend_Controller_Request_Http $request Request data from the controller
     *
     * @return array commonData
     */
    protected function getUserData($request)
    {
        $agent = $request->getServer('HTTP_USER_AGENT');
        list($browser, $version) = explode(' ', static::getBrowser($agent));
        return array(
            'id'               => uniqid('', true),
            'datestamp'        => substr(date('c', strtotime('now')), 0, -6) . 'Z',
            'browser'          => $browser,
            'browserVersion'   => $version,
            'ipaddress'        => $request->getServer('REMOTE_ADDR'),
            'referrer'         => ($request->getServer('HTTP_REFERER') == null)
                ? 'Manual'
                : $request->getServer('HTTP_REFERER'),
            'url'              => $request->getServer('REQUEST_URI'),
            'session'          => Zend_Session::getId()
        );
    }

    /**
     * Parse the browser name and version from the agent string
     *
     * @param string $agent Browser user agent string
     *
     * @return string browser name and version
     */
    public static function getBrowser($agent)
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
            return str_replace('/', ' ', $split[count($split)-2]);
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