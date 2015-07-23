<?php
/**
 * OpenUrl view helper
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
 * OpenUrl view helper
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
     * OpenURL rules
     *
     * @var array
     */
    protected $openUrlRules;

    /**
     * Current RecordDriver
     *
     * @var \VuFind\RecordDriver
     */
    protected $recordDriver;

    /**
     * Constructor
     *
     * @param \VuFind\View\Helper\Root\Context $context      Context helper
     * @param array                            $openUrlRules VuFind OpenURL rules
     * @param \Zend\Config\Config              $config       VuFind OpenURL config
     */
    public function __construct(\VuFind\View\Helper\Root\Context $context,
        $openUrlRules, $config = null
    ) {
        $this->context = $context;
        $this->openUrlRules = $openUrlRules;
        $this->config = $config;
    }

    /**
     * Render appropriate UI controls for an OpenURL link.
     *
     * @param \VuFind\RecordDriver $driver The current recorddriver
     *
     * @return object
     */
    public function __invoke($driver)
    {
        $this->recordDriver = $driver;
        return $this;
    }

    /**
     * Public method to render the OpenURL template
     *
     * @return string
     */
    public function renderTemplate()
    {
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
            'openUrl' => $this->recordDriver->getOpenUrl(),
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
     * Public method to check whether OpenURLs are active for current record
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    public function isActive($area)
    {
        // check first if OpenURLs are enabled for this RecordDriver
        // check second if OpenURLs are enabled for this context
        // check last if any rules apply
        if (!$this->recordDriver->getOpenUrl()
            || !$this->checkContext($area)
            || !$this->checkIfRulesApply()
        ) {
            return false;
        }
        return true;
    }

    /**
     * Does the OpenURL configuration indicate that we should display OpenURLs in
     * the specified context?
     *
     * @param string $area 'results', 'record' or 'holdings'
     *
     * @return bool
     */
    protected function checkContext($area)
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
     * Check if the rulesets found apply to the current record. First match counts.
     *
     * @return bool
     */
    protected function checkIfRulesApply()
    {
        foreach ($this->openUrlRules as $rules) {
            if (!$this->checkExcludedRecordsRules($rules)
                && $this->checkSupportedRecordsRules($rules)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if "exclude" rules from the OpenUrlRules.json file apply to
     * the current record
     *
     * @param array $resolverDriverRules Array of rules for a specific resolverDriver
     *
     * @return bool
     */
    protected function checkExcludedRecordsRules($resolverDriverRules)
    {
        if (isset($resolverDriverRules['exclude'])) {
            // No exclusion rules mean no exclusions -- return false
            return count($resolverDriverRules['exclude'])
                ? $this->checkRules($resolverDriverRules['exclude']) : false;
        }
        return false;
    }

    /**
     * Check if "include" rules from the OpenUrlRules.json file apply to
     * the current record
     *
     * @param array $resolverDriverRules Array of rules for a specific resolverDriver
     *
     * @return bool
     */
    protected function checkSupportedRecordsRules($resolverDriverRules)
    {
        if (isset($resolverDriverRules['include'])) {
            // No inclusion rules mean include everything -- return true
            return count($resolverDriverRules['include'])
                ? $this->checkRules($resolverDriverRules['include']) : true;
        }
        return false;
    }

    /**
     * Checks if rules from the OpenUrlRules.json file apply to the current
     * record
     *
     * @param array $ruleset Array of rules to be checked
     *
     * @return bool
     */
    protected function checkRules($ruleset)
    {
        // check each rule - first rule-match
        foreach ($ruleset as $rule) {

            $ruleMatchCounter = 0;

            // check if current rule is RecordDriver specific
            if (isset($rule['recorddriver'])) {
                if ($this->recordDriver instanceof $rule['recorddriver']) {
                    // get rid of recorddriver field as we have checked the
                    // current rule as being relevant for the current
                    // RecordDriver
                    unset($rule['recorddriver']);
                } else {
                    // skip this rule as it's not relevant for the current
                    // RecordDriver
                    continue;
                }
            }

            // check if defined methods-rules apply for current record
            if (isset($rule['methods'])) {
                foreach ($rule['methods'] as $key => $value) {
                    if (is_callable([$this->recordDriver, $key])) {
                        $recordValue = $this->recordDriver->$key();
                        $value = (array)$value;
                        $recordValue = (array)$recordValue;

                        if (in_array('*', $value)) {
                            // wildcard present
                            if (!count(
                                array_diff(
                                    ['*'],
                                    array_diff($value, $recordValue)
                                )
                            )) {
                                // if explicit defined values existed along with
                                // wildcard those all also existed in recordValue
                                $ruleMatchCounter++;
                            }
                        } else {
                            $valueCount = count($value);
                            if ($valueCount == count($recordValue)
                                && $valueCount == count(
                                    array_intersect($value, $recordValue)
                                )
                            ) {
                                $ruleMatchCounter++;
                            }
                        }
                    }
                }

                if ($ruleMatchCounter == count($rule['methods'])) {
                    // get rid of methods rules as we have checked the
                    // current rule as being relevant for the current
                    // record
                    return true;
                } else {
                    // skip this rule as it's not relevant for the current record
                    continue;
                }
            }

            // if we got this far this rule applies to the current record
            return true;
        }
        // no rule matched
        return false;
    }
}
