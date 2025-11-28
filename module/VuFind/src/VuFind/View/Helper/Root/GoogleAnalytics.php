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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

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
     * Options to pass to the ga() create command.
     *
     * @var string
     */
    protected string $createOptions;

    /**
     * Constructor
     *
     * @param ?string $key     API key (null if disabled)
     * @param array   $options Configuration options (supported option: 'create_options_js').
     */
    public function __construct(protected ?string $key, array $options = [])
    {
        $this->createOptions = $options['create_options_js'] ?? "'auto'";
    }

    /**
     * Returns GA Javascript code.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getRawJavascript()
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
     * @return string
     */
    public function __invoke()
    {
        if (!$this->key) {
            return '';
        }
        $assetManager = $this->getView()->plugin('assetManager');
        $url = 'https://www.googletagmanager.com/gtag/js?id=' . urlencode($this->key);
        $code = $this->getRawJavascript();
        return
            $assetManager->outputInlineScriptLink($url, attrs: ['async' => true]) . "\n"
            . $assetManager->outputInlineScriptString($code);
    }
}
