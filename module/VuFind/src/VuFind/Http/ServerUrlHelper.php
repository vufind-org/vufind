<?php

/**
 * Server URL Helper class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Http
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFind\Http;

use VuFind\ServiceManager\Factory\Autowire;

use function in_array;

/**
 * Server URL Helper class.
 *
 * Partially adapted from Laminas View v2 ServerUrl helper.
 *
 * @category VuFind
 * @package  Http
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class ServerUrlHelper
{
    /**
     * Host (including port).
     *
     * @var ?string
     */
    protected ?string $host = null;

    /**
     * Port.
     *
     * @var ?int
     */
    protected ?int $port = null;

    /**
     * Scheme.
     *
     * @var ?string
     */
    protected ?string $scheme = null;

    /**
     * Constructor.
     *
     * @param bool   $useProxy      Use reverse proxy headers?
     * @param ?array $serverContext Server context (like the $_SERVER superglobal that is used as context by default)
     *
     * @return void
     */
    public function __construct(
        #[Autowire(config: 'config', path: 'Site/reverse_proxy', default: false)]
        protected bool $useProxy,
        protected ?array $serverContext = null,
    ) {
    }

    /**
     * Set server context.
     *
     * @param array $context New server context
     *
     * @return static
     */
    public function setServerContext(array $context): static
    {
        $this->serverContext = $context;
        return $this;
    }

    /**
     * Return the base URL of the current host.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->getScheme() . '://' . ($this->getHost() ?? '');
    }

    /**
     * Return the fully qualified request URL.
     *
     * @return string
     */
    public function getCurrentUrl(): string
    {
        $serverContext = $this->getServerContext();
        $path = $serverContext['REQUEST_URI'] ?? '';
        return $this->getBaseUrl() . (string)$path;
    }

    /**
     * Return the fully qualified URL for the given path on the current host.
     *
     * @param string $path A path beginning at the root, with starting slash.
     *
     * @return string
     */
    public function getUrlForPath(string $path): string
    {
        return $this->getBaseUrl() . $path;
    }

    /**
     * Get server host.
     *
     * @return ?string
     */
    public function getHost(): ?string
    {
        if (null === $this->host) {
            $this->detectHost();
        }
        return $this->host;
    }

    /**
     * Set server host.
     *
     * @param string $host Host
     *
     * @return static
     */
    public function setHost(string $host): static
    {
        $port = $this->getPort();
        $scheme = $this->getScheme();

        if (
            ($scheme === 'http' && (null === $port || $port === 80))
            || ($scheme === 'https' && (null === $port || $port === 443))
        ) {
            $this->host = $host;
            return $this;
        }

        $this->host = $host . ':' . (string)$port;
        return $this;
    }

    /**
     * Get server port.
     *
     * @return ?int
     */
    public function getPort(): ?int
    {
        if (null === $this->port) {
            $this->detectPort();
        }

        return $this->port;
    }

    /**
     * Set server port.
     *
     * @param int $port Post
     *
     * @return static
     */
    public function setPort(int $port): static
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Returns scheme (typically http or https).
     *
     * @return string
     */
    public function getScheme(): string
    {
        if (null === $this->scheme) {
            $this->detectScheme();
        }
        return $this->scheme;
    }

    /**
     * Sets scheme (typically http or https).
     *
     * @param string $scheme Scheme
     *
     * @return static
     */
    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;
        return $this;
    }

    /**
     * Detect the host based on headers.
     *
     * @return void
     */
    protected function detectHost(): void
    {
        if ($this->setHostFromProxy()) {
            return;
        }

        $serverContext = $this->getServerContext();
        $serverPort = $serverContext['SERVER_PORT'] ?? null;
        if ($httpHost = $serverContext['HTTP_HOST'] ?? null) {
            // Detect if the port is set in SERVER_PORT and included in HTTP_HOST
            if (
                $serverPort
                && preg_match('/^(?P<host>.*?):(?P<port>\d+)$/', $httpHost, $matches)
            ) {
                // If they are the same, set the host to just the hostname portion of the Host header.
                if ((int)$matches['port'] === (int)$serverPort) {
                    $this->setHost($matches['host']);
                    return;
                }

                // At this point, we have a SERVER_PORT that differs from the Host header, indicating we likely have a
                // port-forwarding situation. As such, we'll set the host and port from the matched values.
                $this->setPort((int)$matches['port'])
                    ->setHost($matches['host']);
                return;
            }

            $this->setHost($httpHost);
            return;
        }

        if (null !== $serverPort && null !== ($serverName = $serverContext['SERVER_NAME'] ?? null)) {
            $this->setHost($serverName);
        }
    }

    /**
     * Detect if a proxy is in use, and, if so, set the host based on it.
     *
     * @return bool
     */
    protected function setHostFromProxy(): bool
    {
        if (!$this->useProxy) {
            return false;
        }

        $serverContext = $this->getServerContext();
        if (!($host = $serverContext['HTTP_X_FORWARDED_HOST'] ?? null)) {
            return false;
        }

        if (str_contains($host, ',')) {
            $hosts = explode(',', $host);
            $host = trim(array_pop($hosts));
        }
        if (!$host) {
            return false;
        }
        $this->setHost($host);
        return true;
    }

    /**
     * Detect the port.
     *
     * @return void
     */
    protected function detectPort(): void
    {
        if ($this->setPortFromProxy()) {
            return;
        }

        $serverContext = $this->getServerContext();
        if ($port = $serverContext['SERVER_PORT'] ?? null) {
            if ($this->isReverseProxy()) {
                $this->setPort(443);
                return;
            }
            $this->setPort($port);
        }
    }

    /**
     * Set port based on detected proxy headers.
     *
     * @return bool
     */
    protected function setPortFromProxy(): bool
    {
        $serverContext = $this->getServerContext();
        if (
            !$this->useProxy
            || !($port = $serverContext['HTTP_X_FORWARDED_PORT'] ?? null)
        ) {
            return false;
        }

        $this->setPort((int)$port);
        return true;
    }

    /**
     * Detect the scheme.
     *
     * @return void
     */
    protected function detectScheme(): void
    {
        if ($this->setSchemeFromProxy()) {
            return;
        }

        $serverContext = $this->getServerContext();
        if (
            in_array($serverContext['HTTPS'] ?? null, [true, 'on'], true)
            || 'https' === ($serverContext['HTTP_SCHEME'] ?? null)
            || 443 === $this->getPort()
            || $this->isReverseProxy()
        ) {
            $this->setScheme('https');
        } else {
            $this->setScheme('http');
        }
    }

    /**
     * Set the current scheme based on detected proxy headers.
     *
     * @return bool
     */
    protected function setSchemeFromProxy(): bool
    {
        if (!$this->useProxy) {
            return false;
        }

        $serverContext = $this->getServerContext();
        if ($sslHttps = $serverContext['SSL_HTTPS'] ?? null) {
            if (in_array(strtolower($sslHttps), ['on', 1], true)) {
                $this->setScheme('https');
                return true;
            }
        }

        if (!($forwardedProto = $serverContext['HTTP_X_FORWARDED_PROTO'] ?? null)) {
            return false;
        }

        if (!($scheme = trim(strtolower($forwardedProto)))) {
            return false;
        }

        $this->setScheme($scheme);
        return true;
    }

    /**
     * Does the request come via a reverse proxy?
     *
     * @return bool
     */
    protected function isReverseProxy(): bool
    {
        $serverContext = $this->getServerContext();
        return ($serverContext['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https';
    }

    /**
     * Get server context.
     *
     * @return array
     */
    protected function getServerContext(): array
    {
        return $this->serverContext ?? $_SERVER;
    }
}
