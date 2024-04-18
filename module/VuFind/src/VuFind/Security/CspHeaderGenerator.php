<?php

/**
 * Class CspHeaderGenerator
 *
 * PHP version 8
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
use Laminas\Http\Header\GenericHeader;

use function in_array;

/**
 * VuFind class for generating Content Security Policy http headers.
 * Also generates related headers like NEL (network error logging)
 * and reporting headers like Report-To.
 *
 * @category VuFind
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
class CspHeaderGenerator implements
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

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
     * Create all relevant CSP-related headers based on given configuration
     *
     * @return array
     */
    public function getHeaders()
    {
        $headers = [];
        if ($cspHeader = $this->getCspHeader()) {
            $headers[] = $cspHeader;
        }
        if ($reportToHeader = $this->getReportToHeader()) {
            $headers[] = $reportToHeader;
        }
        if ($nelHeader = $this->getNetworkErrorLoggingHeader()) {
            $headers[] = $nelHeader;
        }
        return $headers;
    }

    /**
     * Create CSP header base on given configuration
     *
     * @return ContentSecurityPolicy
     *
     * @deprecated Use getCspHeader instead
     */
    public function getHeader()
    {
        return $this->getCspHeader();
    }

    /**
     * Create CSP header base on given configuration
     *
     * @return ContentSecurityPolicy
     */
    public function getCspHeader()
    {
        $cspHeader = $this->createHeaderObject();
        $directives = $this->config->Directives ?? [];
        if (!$cspHeader || !$directives) {
            return null;
        }
        foreach ($directives as $name => $value) {
            $sources = $value->toArray();
            if (
                in_array($name, $this->scriptDirectives)
                && $this->config->CSP->use_nonce
            ) {
                $sources[] = "'nonce-$this->nonce'";
            }
            // Warn about report-to being used in place of report-uri
            if ($name == 'report-to') {
                foreach ($sources as $source) {
                    if (str_contains($source, '://')) {
                        $this->logWarning('CSP report-to directive should not be a URI.');
                    }
                }
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

    /**
     * Create Report-To header based on given configuration
     *
     * @return ?GenericHeader
     */
    public function getReportToHeader()
    {
        $reportToHeader = new GenericHeader();
        $reportToHeader->setFieldName('Report-To');
        $groupsText = [];

        $reportTo = $this->config->ReportTo;
        foreach ($reportTo['groups'] ?? [] as $groupName) {
            $configSectionName = 'ReportTo' . $groupName;
            $groupConfig = $this->config->$configSectionName ?? false;
            if ($groupConfig) {
                $group = [
                    'group' => $groupName,
                    'max_age' => $groupConfig->max_age ?? 86400, // one day
                    'endpoints' => [],
                ];
                foreach ($groupConfig->endpoints_url ?? [] as $url) {
                    $group['endpoints'][] = [
                        'url' => $url,
                    ];
                }
                $groupsText[] = json_encode($group, JSON_UNESCAPED_SLASHES);
            }
        }

        if (!$groupsText) {
            return null;
        }
        $reportToHeader->setFieldValue(implode(', ', $groupsText));
        return $reportToHeader;
    }

    /**
     * Create NEL (Network Error Logging) header based on given configuration
     *
     * @return ?GenericHeader
     */
    public function getNetworkErrorLoggingHeader()
    {
        $nelHeader = new \Laminas\Http\Header\GenericHeader();
        $nelHeader->setFieldName('NEL');
        $nelData = [];

        $nelConfig = $this->config->NetworkErrorLogging;
        if ($reportTo = $nelConfig['report_to'] ?? null) {
            $nelData['report_to'] = $reportTo;
        } else {
            return null;
        }
        $nelData['max_age'] = $nelConfig['max_age'] ?? 86400; // one day
        if (isset($nelConfig['include_subdomains'])) {
            $nelData['include_subdomains'] = (bool)$nelConfig['include_subdomains'];
        }
        if (isset($nelConfig['failure_fraction'])) {
            $nelData['failure_fraction'] = (float)$nelConfig['failure_fraction'];
        }

        $nelText = json_encode($nelData, JSON_UNESCAPED_SLASHES);
        $nelHeader->setFieldValue($nelText);
        return $nelHeader;
    }
}
