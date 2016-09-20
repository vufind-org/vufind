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
     *
     * @return string
     *
     * @throws \ZendService\ReCaptcha\Exception
     */
    public function getHtml($name = null)
    {
        // Get standard HTML
        $html = parent::getHtml($name);

        // Override placeholder div with richer version:
        $div = '<div class="g-recaptcha" data-sitekey="' . $this->siteKey . '"';
        foreach ($this->options as $key => $option) {
            if ($key == 'lang') {
                continue;
            }
            $div .= ' data-' . $key . '="' . $option . '"';
        }
        $div .= '>';
        $divregex = '/<div[^>]*id=[\'"]recaptcha_widget[\'"][^>]*>/';

        $scriptRegex = '|<script[^>]*></script>|';
        $scriptReplacement = '<script>/*form magic*/</script>';

        return preg_replace(
            [$divregex, $scriptRegex],
            [$div, $scriptReplacement],
            $html
        );
    }
}
