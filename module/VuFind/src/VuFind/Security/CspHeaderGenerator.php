<?php

/**
 * Class CspHeaderGenerator.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Security
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Security;

use function in_array;
use function sprintf;

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
 */
class CspHeaderGenerator implements
    \Psr\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Generated nonce used for one request.
     *
     * @var string
     */
    protected $nonce;

    /**
     * List of directives that can work with nonce.
     *
     * @var string[]
     */
    protected $scriptDirectives = ['script-src', 'script-src-elem'];

    /**
     * Is CSP active?
     *
     * @var bool
     */
    protected bool $active = true;

    /**
     * CspHeaderGenerator constructor.
     *
     * @param array                           $config         Configuration
     * @param \VuFind\Security\NonceGenerator $nonceGenerator Nonce generator
     */
    public function __construct(protected array $config, $nonceGenerator)
    {
        $this->nonce = $nonceGenerator->getNonce();
    }

    /**
     * Is CSP active?
     *
     * @return bool
     */
    public function getActive(): bool
    {
        return $this->active;
    }

    /**
     * Toggle active flag.
     *
     * @param bool $active Active flag
     *
     * @return static
     */
    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Create all relevant CSP-related headers based on given configuration.
     *
     * @return array
     */
    public function getHeaders(): array
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
     * Create CSP header base on given configuration.
     *
     * @return ?array
     */
    public function getCspHeader(): ?array
    {
        if (!($cspHeader = $this->getCSPHeaderName()) || !($directives = $this->config['Directives'] ?? [])) {
            return null;
        }
        $headerDirectives = [];
        foreach ($directives as $name => $sources) {
            if (
                in_array($name, $this->scriptDirectives)
                && ($this->config['CSP']['use_nonce'] ?? null)
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
            $headerDirectives[] = sprintf('%s %s;', $name, implode(' ', $sources));
        }

        return [
            'name' => $cspHeader,
            'value' => str_replace(' ;', ';', implode(' ', $headerDirectives)),
        ];
    }

    /**
     * Get CSP header to use.
     *
     * @return ?string
     */
    protected function getCSPHeaderName(): ?string
    {
        if (!$this->active || !($mode = $this->config['CSP']['enabled'][APPLICATION_ENV] ?? 'report_only')) {
            return null;
        }
        return ('report_only' === $mode)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }

    /**
     * Create Report-To header based on given configuration.
     *
     * @return ?array
     */
    public function getReportToHeader(): ?array
    {
        $groupsText = [];

        $reportTo = $this->config['ReportTo'] ?? [];
        foreach ($reportTo['groups'] ?? [] as $groupName) {
            $configSectionName = 'ReportTo' . $groupName;
            $groupConfig = $this->config[$configSectionName] ?? false;
            if ($groupConfig) {
                $group = [
                    'group' => $groupName,
                    'max_age' => $groupConfig['max_age'] ?? 86400, // one day
                    'endpoints' => [],
                ];
                foreach ($groupConfig['endpoints_url'] ?? [] as $url) {
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

        return [
            'name' => 'Report-To',
            'value' => implode(', ', $groupsText),
        ];
    }

    /**
     * Create NEL (Network Error Logging) header based on given configuration.
     *
     * @return ?array
     */
    public function getNetworkErrorLoggingHeader(): ?array
    {
        $nelConfig = $this->config['NetworkErrorLogging'] ?? [];
        if (!($reportTo = $nelConfig['report_to'] ?? null)) {
            return null;
        }

        $nelData = [
            'report_to' => $reportTo,
            'max_age' => $nelConfig['max_age'] ?? 86400, // one day
        ];
        if (isset($nelConfig['include_subdomains'])) {
            $nelData['include_subdomains'] = (bool)$nelConfig['include_subdomains'];
        }
        if (isset($nelConfig['failure_fraction'])) {
            $nelData['failure_fraction'] = (float)$nelConfig['failure_fraction'];
        }

        $nelText = json_encode($nelData, JSON_UNESCAPED_SLASHES);

        return [
            'name' => 'NEL',
            'value' => $nelText,
        ];
    }
}
