<?php

/**
 * Recaptcha service
 *
 * PHP version 8
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
 * @package  Service
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Service;

use Laminas\ReCaptcha\ReCaptcha as LaminasReCaptcha;

use function func_get_args;
use function is_callable;

/**
 * Recaptcha service
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ReCaptcha
{
    /**
     * Proxied helper
     *
     * @var LaminasRecaptcha
     */
    protected $recaptcha;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->recaptcha = new LaminasReCaptcha(...func_get_args());
    }

    /**
     * Proxy calls to the Laminas ReCaptcha object.
     *
     * @param string $method Method to call
     * @param array  $args   Method arguments
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (is_callable([$this->recaptcha, $method])) {
            return $this->recaptcha->$method(...$args);
        }
        throw new \Exception("Unsupported method: $method");
    }

    /**
     * Get the HTML code for the captcha
     *
     * This method uses the public key to fetch a recaptcha form.
     *
     * @return string
     *
     * @throws \Laminas\ReCaptcha\Exception
     */
    public function getHtml()
    {
        // Get standard HTML
        $html = $this->recaptcha->getHtml();

        // Disable script tag (it is handled by \VuFind\Captcha\ReCaptcha, and
        // we don't want to load duplicate Javascript).
        $scriptRegex = '|<script[^>]*></script>|';
        $scriptReplacement = ''; // remove
        return preg_replace($scriptRegex, $scriptReplacement, $html);
    }
}
