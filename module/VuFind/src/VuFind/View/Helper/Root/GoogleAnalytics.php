<?php

/**
 * GoogleAnalytics view helper
 *
 * PHP version 8
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

use Laminas\View\Helper\HeadScript;

use function is_array;

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
        return <<<JS
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{$this->key}', {$this->createOptions});
            JS;
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
        $inlineScript = $this->getView()->plugin('inlinescript');
        $url = 'https://www.googletagmanager.com/gtag/js?id=' . urlencode($this->key);
        $code = $this->getRawJavascript($customUrl);
        return
            $inlineScript(HeadScript::FILE, $url, 'SET', ['async' => true]) . "\n"
            . $inlineScript(HeadScript::SCRIPT, $code, 'SET');
    }
}
