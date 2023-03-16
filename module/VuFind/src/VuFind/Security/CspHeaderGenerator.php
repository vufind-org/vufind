<?php

/**
 * Class CspHeaderGenerator
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2019.
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
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Security;

use Laminas\Http\Header\ContentSecurityPolicy;
use Laminas\Http\Header\ContentSecurityPolicyReportOnly;

/**
 * VuFind class for generating Content Security Policy http headers
 *
 * @category VuFind
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class CspHeaderGenerator
{
    /**
     * Configuration for generator from contensecuritypolicy.ini
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Generated nonce used for one request
     *
     * @var string
     */
    protected $nonce;

    /**
     * List of directives that can work with nonce
     *
     * @var string[]
     */
    protected $scriptDirectives = ['script-src', 'script-src-elem'];

    /**
     * CspHeaderGenerator constructor.
     *
     * @param \Laminas\Config\Config          $config         Configuration
     * @param \VuFind\Security\NonceGenerator $nonceGenerator Nonce generator
     */
    public function __construct($config, $nonceGenerator)
    {
        $this->nonce = $nonceGenerator->getNonce();
        $this->config = $config;
    }

    /**
     * Create CSP header base on given configuration
     *
     * @return ContentSecurityPolicy
     */
    public function getHeader()
    {
        $cspHeader = $this->createHeaderObject();
        $directives = $this->config->Directives ?? [];
        if (!$cspHeader || !$directives) {
            return null;
        }
        foreach ($directives as $name => $value) {
            $sources = $value->toArray();
            if (in_array($name, $this->scriptDirectives)
                && $this->config->CSP->use_nonce
            ) {
                $sources[] = "'nonce-$this->nonce'";
            }
            // Add report-uri header for backwards compatibility
            if ($name == 'report-to') {
                $cspHeader->setDirective('report-uri', $sources);
            }
            $cspHeader->setDirective($name, $sources);
        }
        return $cspHeader;
    }

    /**
     * Create header object
     *
     * @return ContentSecurityPolicy
     */
    protected function createHeaderObject()
    {
        $mode = $this->config->CSP->enabled[APPLICATION_ENV] ?? 'report_only';
        if (!$mode) {
            return null;
        }
        return ('report_only' === $mode)
            ? new ContentSecurityPolicyReportOnly()
            : new ContentSecurityPolicy();
    }
}
