<?php
/**
 * Recaptcha service
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Service;

/**
 * Recaptcha service
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptcha extends \LosReCaptcha\Service\ReCaptcha
{
    /**
     * Get the HTML code for the captcha
     *
     * This method uses the public key to fetch a recaptcha form.
     *
     * @param null|string $name Base name for recaptcha form elements
     * @return string
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function getHtml($name = null)
    {
        if ($this->siteKey === null) {
            throw new Exception('Missing public key');
        }

        $host = self::API_SERVER;

        if ((bool) $this->params['ssl'] === true) {
            $host = self::API_SECURE_SERVER;
        }

        $htmlBreak = '<br>';
        $htmlInputClosing = '>';

        if ((bool) $this->params['xhtml'] === true) {
            $htmlBreak = '<br />';
            $htmlInputClosing = '/>';
        }

        $langOption = '';

        if (isset($this->options['lang']) && !empty($this->options['lang'])) {
            $langOption = "?hl={$this->options['lang']}";
        }

        error_log(print_r($this->options, true));
        $return = '<div id="recaptcha_widget" class="g-recaptcha" data-sitekey="'
            . $this->siteKey . '"';
        foreach ($this->options as $key => $option) {
            $return .= ' data-' . $key . '="' . $option . '"';
        }
        $return .= <<<HTML
></div>
<noscript>
    <div style="width: 302px; height: 352px;">
        <div style="width: 302px; height: 352px; position: relative;">
            <div style="width: 302px; height: 352px; position: absolute;">
                <iframe src="{$host}/fallback?k={$this->siteKey}" frameborder="0" scrolling="no" style="width: 302px; height:352px; border-style: none;"></iframe>
            </div>
            <div style="width: 250px; height: 80px; position: absolute; border-style: none; bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
                <textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 80px; border: 1px solid #c1c1c1; margin: 0px; padding: 0px; resize: none;" value=""></textarea>
            </div>
        </div>
    </div>
</noscript>
<script type="text/javascript" src="{$host}.js{$langOption}" async defer></script>
HTML;

        return $return;
    }
}
