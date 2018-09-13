<?php
/**
 * OpenUrl view helper
 *
 * PHP version 7
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\View\Helper\Root;

use VuFind\Resolver\Driver\PluginManager;

/**
 * OpenUrl view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
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
     * Resolver plugin manager
     *
     * @var PluginManager
     */
    protected $resolverPluginManager;

    /**
     * Current RecordDriver
     *
     * @var \VuFind\RecordDriver
     */
    protected $recordDriver;

    /**
     * OpenURL context ('results', 'record' or 'holdings')
     *
     * @var string
     */
    protected $area;

    /**
     * Constructor
     *
     * @param Context             $context       Context helper
     * @param array               $openUrlRules  VuFind OpenURL rules
     * @param PluginManager       $pluginManager Resolver plugin manager
     * @param \Zend\Config\Config $config        VuFind OpenURL config
     */
    public function __construct(Context $context, $openUrlRules,
        PluginManager $pluginManager, $config = null
    ) {
        $this->context = $context;
        $this->openUrlRules = $openUrlRules;
        $this->resolverPluginManager = $pluginManager;
        $this->config = $config;
    }

    /**
     * Set up context for helper
     *
     * @param \VuFind\RecordDriver $driver The current record driver
     * @param string               $area   OpenURL context ('results', 'record'
     *  or 'holdings'
     *
     * @return object
     */
    public function __invoke($driver, $area)
    {
        $this->recordDriver = $driver;
        $this->area = $area;
        return $this;
    }

    /**
     * Support method for renderTemplate() -- process image based parameters.
     *
     * @param bool  $imagebased Indicates if an image based link
     * should be displayed or not (null for system default)
     * @param array $params     OpenUrl parameters set so far
     *
     * @return void
     */
    protected function addImageBasedParams($imagebased, & $params)
    {
        $params['openUrlImageBasedMode'] = $this->getImageBasedLinkingMode();
        $params['openUrlImageBasedSrc'] = null;

        if (null === $imagebased) {
            $imagebased = $this->imageBasedLinkingIsActive();
        }

        if ($imagebased) {
            if (!isset($this->config->dynamic_graphic)) {
                // if imagebased linking is forced by the template, but it is not
                // configured properly, throw an exception
                throw new \Exception(
                    'Template tries to display OpenURL as image based link, but
                     Image based linking is not configured! Please set parameter
                     dynamic_graphic in config file.'
                );
            }

            // Check if we have an image-specific OpenURL to use to override
            // the default value when linking the image.
            $params['openUrlImageBasedOverride'] = $this->recordDriver
                ->tryMethod('getImageBasedOpenUrl');

            // Concatenate image based OpenUrl base and OpenUrl
            // to a usable image reference
            $base = $this->config->dynamic_graphic;
            $imageOpenUrl = $params['openUrlImageBasedOverride']
                ? $params['openUrlImageBasedOverride'] : $params['openUrl'];
            $params['openUrlImageBasedSrc'] = $base
                . ((false === strpos($base, '?')) ? '?' : '&')
                . $imageOpenUrl;
        }

        return $params;
    }

    /**
     * Public method to render the OpenURL template
     *
     * @param bool $imagebased Indicates if an image based link
     * should be displayed or not (null for system default)
     *
     * @return string
     */
    public function renderTemplate($imagebased = null)
    {
        if (null !== $this->config && isset($this->config->url)) {
            // Trim off any parameters (for legacy compatibility -- default config
            // used to include extraneous parameters):
            list($base) = explode('?', $this->config->url);
        } else {
            $base = false;
        }

        $embed = (isset($this->config->embed) && !empty($this->config->embed));

        $embedAutoLoad = isset($this->config->embed_auto_load)
            ? $this->config->embed_auto_load : false;
        // ini values 'true'/'false' are provided via ini reader as 1/0
        // only check embedAutoLoad for area if the current area passed checkContext
        if (!($embedAutoLoad === "1" || $embedAutoLoad === "0")
            && !empty($this->area)
        ) {
            // embedAutoLoad is neither true nor false, so check if it contains an
            // area string defining where exactly to use autoloading
            $embedAutoLoad = in_array(
                strtolower($this->area),
                array_map(
                    'trim',
                    array_map(
                        'strtolower',
                        explode(',', $embedAutoLoad)
                    )
                )
            );
        }

        // instantiate the resolver plugin to get a proper resolver link
        $resolver = isset($this->config->resolver)
            ? $this->config->resolver : 'other';
        $openurl = $this->recordDriver->getOpenUrl();
        if ($this->resolverPluginManager->has($resolver)) {
            $resolverObj = new \VuFind\Resolver\Connection(
                $this->resolverPluginManager->get($resolver)
            );
            $resolverUrl = $resolverObj->getResolverUrl($openurl);
        } else {
            $resolverUrl = empty($base) ? '' : $base . '?' . $openurl;
        }

        // Build parameters needed to display the control:
        $params = [
            'resolverUrl' => $resolverUrl,
            'openUrl' => $openurl,
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
            'openUrlEmbedAutoLoad' => $embedAutoLoad
        ];
        $this->addImageBasedParams($imagebased, $params);

        // Render the subtemplate:
        return $this->context->__invoke($this->getView())->renderInContext(
            'Helpers/openurl.phtml', $params
        );
    }

    /**
     * Public method to check ImageBased Linking mode
     *
     * @return string|bool false if image based linking is not active,
     * config image_based_linking_mode otherwise (default = 'both')
     */
    public function getImageBasedLinkingMode()
    {
        if ($this->imageBasedLinkingIsActive()
            && isset($this->config->image_based_linking_mode)
        ) {
            return $this->config->image_based_linking_mode;
        }
        return $this->imageBasedLinkingIsActive() ? 'both' : false;
    }

    /**
     * Public method to check if ImageBased Linking is enabled
     *
     * @return bool
     */
    public function imageBasedLinkingIsActive()
    {
        return isset($this->config->dynamic_graphic);
    }

    /**
     * Public method to check whether OpenURLs are active for current record
     *
     * @return bool
     */
    public function isActive()
    {
        // check first if OpenURLs are enabled for this RecordDriver
        // check second if OpenURLs are enabled for this context
        // check last if any rules apply
        if (!$this->recordDriver->getOpenUrl()
            || !$this->checkContext()
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
     * @return bool
     */
    protected function checkContext()
    {
        // Doesn't matter the target area if no OpenURL resolver is specified:
        if (!isset($this->config->url)) {
            return false;
        }

        // If a setting exists, return that:
        $key = 'show_in_' . $this->area;
        if (isset($this->config->$key)) {
            return $this->config->$key;
        }

        // If we got this far, use the defaults -- true for results, false for
        // everywhere else.
        return $this->area == 'results';
    }

    /**
     * Check if the rulesets found apply to the current record. First match counts.
     *
     * @return bool
     */
    protected function checkIfRulesApply()
    {
        // special case if no rules are defined at all assume that any record is
        // valid for openUrls
        if (!isset($this->openUrlRules) || count($this->openUrlRules) < 1) {
            return true;
        }
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
     * Check if an array contains a non-empty value.
     *
     * @param array $in Array to check
     *
     * @return bool
     */
    protected function hasNonEmptyValue($in)
    {
        foreach ($in as $current) {
            if (!empty($current)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if method rules match.
     *
     * @param array $rules Rules to check.
     *
     * @return bool
     */
    protected function checkMethodRules($rules)
    {
        $ruleMatchCounter = 0;
        foreach ($rules as $key => $value) {
            if (is_callable([$this->recordDriver, $key])) {
                $value = (array)$value;
                $recordValue = (array)$this->recordDriver->$key();

                // wildcard present
                if (in_array('*', $value)) {
                    // Strip the wildcard out of the value list; what is left
                    // is the set of values that MUST be found in the record.
                    // If we subtract the record values from the required values
                    // and still have something left behind, then the match fails
                    // as long as SOME non-empty value was provided.
                    $requiredValues = array_diff($value, ['*']);
                    if (!count(array_diff($requiredValues, $recordValue))
                        && $this->hasNonEmptyValue($recordValue)
                    ) {
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

        // Did all the rules match?
        return $ruleMatchCounter == count($rules);
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
            // skip this rule if it's not relevant for the current RecordDriver
            if (isset($rule['recorddriver'])
                && !($this->recordDriver instanceof $rule['recorddriver'])
            ) {
                continue;
            }

            // check if defined methods-rules apply for current record
            if (isset($rule['methods'])) {
                if ($this->checkMethodRules($rule['methods'])) {
                    return true;
                }
            } else {
                // no method rules? Then assume a match by default!
                return true;
            }
        }
        // no rule matched
        return false;
    }
}
