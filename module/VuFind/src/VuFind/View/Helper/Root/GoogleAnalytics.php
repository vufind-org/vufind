<?php
/**
 * GoogleAnalytics view helper
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

/**
 * GoogleAnalytics view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GoogleAnalytics extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * API key (false if disabled)
     *
     * @var string|bool
     */
    protected $key;

    /**
     * Are we using Universal Analytics?
     *
     * @var bool
     */
    protected $universal;

    /**
     * Options to pass to the ga() create command.
     *
     * @var string
     */
    protected $createOptions;

    /**
     * Constructor
     *
     * @param string|bool $key     API key (false if disabled)
     * @param bool|array  $options Configuration options (supported options:
     * 'universal' and 'create_options_js'). If a boolean is provided instead of
     * an array, that value is used as the 'universal' setting and no other options
     * are set (for backward compatibility).
     */
    public function __construct($key, $options = [])
    {
        // The second constructor parameter used to be a boolean representing
        // the "universal" setting, so convert to an array for back-compatibility:
        if (!is_array($options)) {
            $options = ['universal' => (bool)$options];
        }
        $this->key = $key;
        $this->universal = $options['universal'] ?? false;
        $this->createOptions = $options['create_options_js'] ?? "'auto'";
    }

    /**
     * Returns GA Javascript code.
     *
     * @param string $customUrl override URL to send to Google Analytics
     *
     * @return string
     */
    protected function getRawJavascript($customUrl = false)
    {
        // Simple case: Universal
        if ($this->universal) {
            return '(function(i,s,o,g,r,a,m){'
                . "i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){"
                . '(i[r].q=i[r].q||[]).push(arguments)},'
                . 'i[r].l=1*new Date();a=s.createElement(o),'
                . 'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;'
                . 'm.parentNode.insertBefore(a,m)'
                . "})(window,document,'script',"
                . "'//www.google-analytics.com/analytics.js','ga');"
                . "ga('create', '{$this->key}', {$this->createOptions});"
                . "ga('send', 'pageview');";
        }

        // Alternate (legacy) case:
        $code = 'var key = "' . $this->key . '";' . "\n"
            . "var _gaq = _gaq || [];\n"
            . "_gaq.push(['_setAccount', key]);\n";
        if ($customUrl) {
            $code .= "_gaq.push(['_trackPageview', '" . $customUrl . "']);\n";
        } else {
            $code .= "_gaq.push(['_trackPageview']);\n";
        }
        $code .= "(function() {\n"
            . "var ga = document.createElement('script'); "
            . "ga.type = 'text/javascript'; ga.async = true;\n"
            . "ga.src = ('https:' == document.location.protocol ? "
            . "'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\n"
            . "var s = document.getElementsByTagName('script')[0]; "
            . "s.parentNode.insertBefore(ga, s);\n"
            . "})();";
        return $code;
    }

    /**
     * Returns GA code (if active) or empty string if not.
     *
     * @param string $customUrl override URL to send to Google Analytics
     *
     * @return string
     */
    public function __invoke($customUrl = false)
    {
        if (!$this->key) {
            return '';
        }
        $code = $this->getRawJavascript($customUrl);
        $inlineScript = $this->getView()->plugin('inlinescript');
        return $inlineScript(\Laminas\View\Helper\HeadScript::SCRIPT, $code, 'SET');
    }
}
