<?php
/**
 * GoogleAnalytics view helper
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
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\View\Helper\Root;

/**
 * GoogleAnalytics view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class GoogleAnalytics extends \Zend\View\Helper\AbstractHelper
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
     * Constructor
     *
     * @param string|bool $key       API key (false if disabled)
     * @param bool        $universal Are we using Universal Analytics?
     */
    public function __construct($key, $universal = false)
    {
        $this->key = $key;
        $this->universal = $universal;
    }

    /**
     * Returns GA code (if active) or empty string if not.
     *
     * @return string
     */
    public function __invoke()
    {
        if (!$this->key) {
            return '';
        }
        if (!$this->universal) {
            $code = 'var key = "' . $this->key . '";' . "\n"
                . "var _gaq = _gaq || [];\n"
                . "_gaq.push(['_setAccount', key]);\n"
                . "_gaq.push(['_trackPageview']);\n"
                . "(function() {\n"
                . "var ga = document.createElement('script'); "
                . "ga.type = 'text/javascript'; ga.async = true;\n"
                . "ga.src = ('https:' == document.location.protocol ? "
                . "'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\n"
                . "var s = document.getElementsByTagName('script')[0]; "
                . "s.parentNode.insertBefore(ga, s);\n"
                . "})();";
        } else {
            $code = '(function(i,s,o,g,r,a,m){'
                . "i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){"
                . '(i[r].q=i[r].q||[]).push(arguments)},'
                . 'i[r].l=1*new Date();a=s.createElement(o),'
                . 'm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;'
                . 'm.parentNode.insertBefore(a,m)'
                . "})(window,document,'script',"
                . "'//www.google-analytics.com/analytics.js','ga');"
                . "ga('create', '{$this->key}', 'auto');"
                . "ga('send', 'pageview');";
        }
        $inlineScript = $this->getView()->plugin('inlinescript');
        return $inlineScript(\Zend\View\Helper\HeadScript::SCRIPT, $code, 'SET');
    }
}