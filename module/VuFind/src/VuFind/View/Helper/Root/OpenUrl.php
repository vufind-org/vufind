<?php
/**
 * OpenURL view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * OpenURL view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OpenUrl extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Context helper
     *
     * @var \VuFind\View\Helper\Root\Context
     */
    protected $context;

    /**
     * VuFind OpenURL configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\View\Helper\Root\Context $context Context helper
     * @param \Zend\Config\Config              $config  VuFind OpenURL configuration
     */
    public function __construct(\VuFind\View\Helper\Root\Context $context,
        $config = null
    ) {
        $this->context = $context;
        $this->config = $config;
    }

    /**
     * Render appropriate UI controls for an OpenURL link.
     *
     * @param string $openUrl The OpenURL to display
     * @param string $area    The area where OpenURLs are to be displayed
     *
     * @return string
     */
    public function __invoke($openUrl, $area)
    {
        // check first if OpenURLs are enabled for this context
        // check second if any excluded_records rule applies
        // check last if this record is supported
        if (!$this->openURLActive($area)
            || $this->openURLCheckExcludedRecordsRules()
            || !$this->openURLCheckSupportedRecordsRules()
        ) {
            return false;
        }

        // Static counter to ensure that each OpenURL gets a unique ID.
        static $counter = 0;

        if (null !== $this->config && isset($this->config->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $this->config->url);
        } else {
            $base = false;
        }

        $embed = (isset($this->config->embed) && !empty($this->config->embed));
        if ($embed) {
            $counter++;
        }

        // Build parameters needed to display the control:
        $params = [
            'openUrl' => $openUrl,
            'openUrlBase' => empty($base) ? false : $base,
            'openUrlWindow' => empty($this->config->window_settings)
                ? false : $this->config->window_settings,
            'openUrlGraphic' => empty($this->config->graphic)
                ? false : $this->config->graphic,
            'openUrlGraphicWidth' => empty($this->config->graphic_width)
                ? false : $this->config->graphic_width,
            'openUrlGraphicHeight' => empty($this->config->graphic_height)
                ? false : $this->config->graphic_height,
            'openUrlEmbed' => $embed,
            'openUrlId' => $counter
        ];

        // Render the subtemplate:
        return $this->context->__invoke($this->getView())->renderInContext(
            'Helpers/openurl.phtml', $params
        );
    }

    /**
     * Does the OpenURL configuration indicate that we should display OpenURLs in
     * the specified context?
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    protected function openURLActive($area)
    {
        // Doesn't matter the target area if no OpenURL resolver is specified:
        if (!isset($this->config->url)) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $area;
        if (isset($this->config->$key)) {
            return $this->config->$key;
        }

        // If we got this far, use the defaults -- true for results, false for
        // everywhere else.
        return ($area == 'results');
    }

    /**
     * Check if excluded_records rules from the OpenURL config.ini section apply to
     * the current record
     *
     * @return bool
     */
    protected function openURLCheckExcludedRecordsRules()
    {
        if (isset($this->config)
            && isset($this->config->excluded_records)
        ) {
            $excluded_records
                = $this->config->excluded_records->toArray();
            return $this->openURLCheckRules($excluded_records);
        }
        return false;
    }

    /**
     * Check if supported_records rules from the OpenURL config.ini section apply to
     * the current record
     *
     * @return bool
     */
    protected function openURLCheckSupportedRecordsRules()
    {
        if (isset($this->config)
            && isset($this->config->supported_records)
        ) {
            $supported_records
                = $this->config->supported_records->toArray();
            return $this->openURLCheckRules($supported_records);
        }
        return false;
    }

    /**
     * Checks if rules from the OpenURL config.ini section apply to the current
     * record
     *
     * @param array $ruleset Array of rules to be checked
     *
     * @return bool
     */
    protected function openURLCheckRules($ruleset)
    {
        if (count($ruleset)) {
            // check each rule - first rule-match
            foreach ($ruleset as $rule) {
                $ruleArray = json_decode($rule, true);

                $ruleMatchCounter = 0;

                foreach ($ruleArray as $key => $value) {
                    if (method_exists($this->view->driver, $key)) {
                        $recordValue = $this->view->driver->$key();
                        if ($value === "*" && $recordValue) {
                            // wildcard value
                            $ruleMatchCounter++;
                        } elseif (!count(
                            array_diff((array)$value, (array)$recordValue)
                        )) {
                            // any other value
                            $ruleMatchCounter++;
                        }
                    }
                }

                if ($ruleMatchCounter == count($ruleArray)) {
                    // this rule matched
                    return true;
                }
            }
            // no rule matched
            return false;
        }
        // non-existing rules match always
        return true;
    }
}