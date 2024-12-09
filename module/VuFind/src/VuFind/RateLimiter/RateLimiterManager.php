<?php

/**
 * Rate limiter manager.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Cache
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\RateLimiter;

use Closure;
use Laminas\EventManager\EventInterface;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Mvc\MvcEvent;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Net\IpAddressUtils;

use function in_array;
use function is_bool;

/**
 * Rate limiter manager.
 *
 * @category VuFind
 * @package  Cache
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RateLimiterManager implements LoggerAwareInterface, TranslatorAwareInterface
{
    use LoggerAwareTrait;
    use TranslatorAwareTrait;

    /**
     * Current event description for logging
     *
     * @var string
     */
    protected $eventDesc = '??';

    /**
     * Client details for logging
     *
     * @var string
     */
    protected $clientLogDetails;

    /**
     * Constructor
     *
     * @param array          $config                     Rate limiter configuration
     * @param string         $clientIp                   Client's IP address
     * @param ?int           $userId                     User ID or null if not logged in
     * @param Closure        $rateLimiterFactoryCallback Rate limiter factory callback
     * @param IpAddressUtils $ipUtils                    IP address utilities
     */
    public function __construct(
        protected array $config,
        protected string $clientIp,
        protected ?int $userId,
        protected Closure $rateLimiterFactoryCallback,
        protected IpAddressUtils $ipUtils
    ) {
        $this->clientLogDetails = "ip:$clientIp";
        if (null !== $userId) {
            $this->clientLogDetails .= " u:$userId";
        }
    }

    /**
     * Check if rate limiter is enabled
     *
     * @return bool|string False if disabled, true if enabled and enforcing,
     * 'report_only' if enabled for logging only (not enforcing the limits)
     */
    public function isEnabled(): bool|string
    {
        $mode = $this->config['General']['enabled'] ?? false;
        return is_bool($mode) ? $mode : (string)$mode;
    }

    /**
     * Check if the given event is allowed
     *
     * @param EventInterface $event Event
     *
     * @return array Associative array with the following keys:
     *   bool    allow              Whether to allow the request
     *   ?int    requestsRemaining  Remaining requests
     *   ?int    retryAfter         Retry after seconds if limit exceeded
     *   ?int    requestLimit       Current limit
     *   ?string message            Response message if limit reached
     */
    public function check(EventInterface $event): array
    {
        $result = [
            'allow' => true,
            'requestsRemaining' => null,
            'retryAfter' => null,
            'requestLimit' => null,
            'message' => null,
        ];

        if (!$this->isEnabled() || !($event instanceof MvcEvent)) {
            return $result;
        }
        $routeMatch = $event->getRouteMatch();
        $controller = $routeMatch?->getParam('controller') ?? '??';
        $action = ($routeMatch?->getParam('action') ?? '??');
        $this->eventDesc = "$controller/$action";
        if ('AJAX' === $controller && 'JSON' === $action) {
            $req = $event->getRequest();
            $method = $req->getPost('method') ?? $req->getQuery('method');
            $this->eventDesc .= " $method";
        }
        try {
            // Check for a matching policy:
            if (!($policyId = $this->getPolicyIdForEvent($event))) {
                $this->verboseDebug('No policy matches event');
                return $result;
            }
            // We have a policy matching the route, so check rate limiter:
            $limiter = ($this->rateLimiterFactoryCallback)($this->config, $policyId, $this->clientIp, $this->userId);
            $limit = $limiter->consume(1);
            $result = [
                'allow' => true,
                'requestsRemaining' => $limit->getRemainingTokens(),
                'retryAfter' => $limit->getRetryAfter()->getTimestamp() - time(),
                'requestLimit' => $limit->getLimit(),
            ];
            $this->verboseDebug(
                ($limit->isAccepted() ? 'Accepted' : 'Refused')
                . " by policy '$policyId'"
                . ', remaining: ' . $result['requestsRemaining']
                . ', retry-after: ' . $result['retryAfter']
                . ', limit: ' . $result['requestLimit']
            );

            // Add headers if configured:
            if ($this->config['Policies'][$policyId]['addHeaders'] ?? false) {
                $headers = $event->getResponse()->getHeaders();
                $headers->addHeaders(
                    [
                        'X-RateLimit-Remaining' => $result['requestsRemaining'],
                        'X-RateLimit-Retry-After' => $result['retryAfter'],
                        'X-RateLimit-Limit' => $result['requestLimit'],
                    ]
                );
            }
            if ($limit->isAccepted()) {
                return $result;
            }
            $logMsg = "$this->eventDesc: $this->clientLogDetails policy '$policyId' exceeded";
            if ('report_only' === $this->isEnabled() || ($this->config['Policies'][$policyId]['reportOnly'] ?? false)) {
                $this->logWarning("$logMsg (not enforced)");
                return $result;
            }
            $this->logWarning("$logMsg (enforced)");
            $result['allow'] = false;
            $result['message'] = $this->getTooManyRequestsResponseMessage($event, $result);
            return $result;
        } catch (\Exception $e) {
            $this->logError((string)$e);
        }
        // Allow access on failure:
        return $result;
    }

    /**
     * Try to find a policy that matches an event
     *
     * @param MvcEvent $event Event
     *
     * @return ?string policy id or null if no match
     */
    protected function getPolicyIdForEvent(MvcEvent $event): ?string
    {
        $isCrawler = null;
        foreach ($this->config['Policies'] ?? [] as $name => $settings) {
            if (null !== ($loggedIn = $settings['loggedIn'] ?? null)) {
                if ($loggedIn !== ($this->userId ? true : false)) {
                    continue;
                }
            }
            if (null !== ($crawler = $settings['crawler'] ?? null)) {
                $isCrawler ??= $this->isCrawlerRequest($event);
                if ($crawler !== $isCrawler) {
                    continue;
                }
            }
            if ($ipRanges = $settings['ipRanges'] ?? null) {
                if (!$this->ipUtils->isInRange($this->clientIp, (array)$ipRanges)) {
                    continue;
                }
            }

            if (!($filters = $settings['filters'] ?? null)) {
                return $name;
            }
            foreach ($filters as $filter) {
                if ($this->eventMatchesFilter($event, $filter)) {
                    return $name;
                }
            }
        }
        return null;
    }

    /**
     * Check if an event matches a filter
     *
     * @param MvcEvent $event  Event
     * @param array    $filter Filter from configuration
     *
     * @return bool
     */
    protected function eventMatchesFilter(MvcEvent $event, array $filter): bool
    {
        $routeMatch = $event->getRouteMatch();
        foreach ($filter as $param => $value) {
            if ('name' === $param) {
                if ($routeMatch?->getMatchedRouteName() !== $value) {
                    return false;
                }
            } elseif (in_array($param, ['params', 'query', 'post'])) {
                $req = $event->getRequest();
                $allParams = match ($param) {
                    'query' => $req->getQuery()->toArray(),
                    'post' => $req->getPost()->toArray(),
                    default => $req->getPost()->toArray() + $req->getQuery()->toArray(),
                };
                foreach ($value as $key => $val) {
                    if ($val !== $allParams[$key] ?? null) {
                        return false;
                    }
                }
            } elseif ($routeMatch?->getParam($param) !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Log a verbose debug message if configured
     *
     * @param string $msg Message
     *
     * @return void
     */
    protected function verboseDebug(string $msg): void
    {
        if ($this->config['General']['verbose'] ?? false) {
            $this->log('debug', "$this->eventDesc [$this->clientLogDetails]: $msg", [], true);
        }
    }

    /**
     * Get a response message for too many requests
     *
     * @param MvcEvent $event  Request event
     * @param array    $result Rate limiter result
     *
     * @return string
     */
    protected function getTooManyRequestsResponseMessage(MvcEvent $event, array $result): string
    {
        if ($result['retryAfter']) {
            $msg = $this->translate('error_too_many_requests_retry_after', ['%%seconds%%' => $result['retryAfter']]);
        } else {
            $msg = $this->translate('error_too_many_requests');
        }
        $routeMatch = $event->getRouteMatch();
        if ($routeMatch?->getParam('controller') === 'AJAX' && $routeMatch?->getParam('action') === 'JSON') {
            return json_encode(['error' => $msg]);
        }
        return $msg;
    }

    /**
     * Check if the request is from a crawler
     *
     * @param MvcEvent $event Request event
     *
     * @return bool
     */
    protected function isCrawlerRequest(MvcEvent $event): bool
    {
        $headers = $event->getRequest()->getHeaders();
        if (!$headers->has('User-Agent')) {
            return false;
        }
        $agent = $headers->get('User-Agent')->getFieldValue();
        $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
        return $crawlerDetect->isCrawler($agent);
    }
}
