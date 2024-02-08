<?php

/**
 * Content Security Policy view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Http\Response;

/**
 * Content Security Policy view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Csp extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param ?Response $response HTTP Response, if any
     * @param string    $nonce    CSP nonce
     */
    public function __construct(protected ?Response $response, protected string $nonce)
    {
    }

    /**
     * Disable Content Security Policy by removing the headers
     *
     * @return void
     */
    public function disablePolicy(): void
    {
        if (null === $this->response) {
            return;
        }
        $headers = $this->response->getHeaders();
        $fieldsToCheck = [
            'Content-Security-Policy',
            'Content-Security-Policy-Report-Only',
        ];
        foreach ($fieldsToCheck as $field) {
            if ($cspHeaders = $headers->get($field)) {
                // Make sure the result is iterable (an array cast doesn't work here
                // as a single header may be castable as an array):
                $headerArray = $cspHeaders instanceof \ArrayIterator
                    ? $cspHeaders : [$cspHeaders];
                foreach ($headerArray as $header) {
                    $headers->removeHeader($header);
                }
            }
        }
    }

    /**
     * Return the current nonce
     *
     * Result is a base64 encoded string that does not need escaping.
     *
     * @return string
     */
    public function getNonce(): string
    {
        return $this->nonce;
    }
}
