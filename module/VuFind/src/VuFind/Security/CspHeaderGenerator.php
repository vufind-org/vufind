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
 * @package  VuFind\Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\Security;

use Laminas\Http\Header\ContentSecurityPolicy;
use VuFind\Http\Header\ContentSecurityPolicyReportOnly;

/**
 * VuFind class for generating Content Security Policy http headers
 *
 * @category VuFind
 * @package  VuFind\Security
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
        if ('development' == APPLICATION_ENV) {
            return $this->createDevelopmentModeHeader();
        }
        return $this->createStandardHeader();
    }

    /**
     * Create CSP header base on given configuration
     *
     * @return ContentSecurityPolicy
     */
    protected function createStandardHeader()
    {
        $cspHeader = $this->createHeaderObject();
        $directives = $this->config->Directives;
        foreach ($directives as $name => $value) {
            $sources = $value->toArray();
            if ($name == "script-src" && $this->config->CSP->use_nonce) {
                $sources[] = "'nonce-$this->nonce'";
            }
            $cspHeader->setDirective($name, $sources);
        }
        return $cspHeader;
    }

    /**
     * Create CSP header base for development mode
     *
     * @return ContentSecurityPolicy
     */
    protected function createDevelopmentModeHeader()
    {
        $cspHeader = $this->createHeaderObject();
        $cspHeader->setDirective('script-src', ["'self'", "'unsafe-inline'"]);
        $cspHeader->setDirective('style-src', ["'self'", "'unsafe-inline'"]);
        return $cspHeader;
    }

    /**
     * Create header object
     *
     * @return ContentSecurityPolicy
     */
    protected function createHeaderObject()
    {
        if ($this->config->CSP->report_only) {
            $cspHeader = new ContentSecurityPolicyReportOnly();
        } else {
            $cspHeader = new ContentSecurityPolicy();
        }
        return $cspHeader;
    }
}
