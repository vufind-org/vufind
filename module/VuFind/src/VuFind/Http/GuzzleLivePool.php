<?php

/**
 * GuzzleHTTP Live Promise Pool
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2026.
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
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function array_shift;
use function count;
use function spl_object_id;

/**
 * GuzzleHTTP live promises pool.
 *
 * Allows for a pool of request promises, including actively adding new requests
 * while the existing requests are being executed. Has a max concurrency to allow
 * for excess queuing of requests without an excess of active requests in transit.
 *
 * @category VuFind
 * @package  Http
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GuzzleLivePool
{
    /**
     * The GuzzleHTTP client instance the pool uses
     *
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * The maximum number of active requests allowed concurrently
     *
     * @var int
     */
    private int $concurrency;

    /**
     * The queue of requests which need to be fulfilled; an array which includes
     * the Request, the options to pass to the request, and the Promise issued which
     * needs to fulfill the request.
     *
     * @var array<array{request: RequestInterface, options: array, deferred: Promise}>
     */
    private array $queue = [];

    /**
     * The currently active promises; not to exceed $concurrency.
     *
     * @var array
     */
    private array $activePromises = [];

    /**
     * Constructor
     *
     * @param ClientInterface $client      The GuzzleHTTP client for the pool to use
     * @param int             $concurrency Max number of concurrent API calls
     */
    public function __construct(ClientInterface $client, int $concurrency = 10)
    {
        $this->client = $client;
        $this->concurrency = $concurrency < 1 ? 1 : $concurrency;
    }

    /**
     * Add a request to the queue, returning a promise for that request.
     *
     * @param RequestInterface $request A request to create a promise for
     * @param array            $options Options for the request. If 'synchronous' is not
     *                                  specified, it will default to false (recommended).
     *
     * @return Promise A promise which fill be fulfilled by another promise yet to be
     *                 created, but will be created when there is room in the queue.
     *                 From a developers perspective, you can use the returned promise
     *                 like you would a normal GuzzleHTTP promise.
     */
    public function add(RequestInterface $request, array $options = []): Promise
    {
        $deferred = new Promise(function () use (&$deferred): void {
            /* This code handles if a developer calls wait() on a promise which
             * exists solely in the queue. The promise hasn't begun yet, so
             * there is nothing to "wait" for. Instead, we start waiting on
             * active promises until our queue promise become active. */
            while ($deferred->getState() === PromiseInterface::PENDING) {
                $this->waitOnNextPromise();
            }
        });
        $this->queue[] = [
            'request'  => $request,
            'options'  => $options,
            'deferred' => $deferred,
        ];
        $this->advance();
        return $deferred;
    }

    /**
     * Check if there is available capacity to run a queued request and dispatch it if there is
     *
     * @return void
     */
    protected function advance(): void
    {
        while (count($this->activePromises) < $this->concurrency && !empty($this->queue)) {
            $item = array_shift($this->queue);
            $this->dispatch($item['request'], $item['options'], $item['deferred']);
        }
    }

    /**
     * Add a request to the queue, returning a promise for that request.
     *
     * @param RequestInterface $request  A request to create a promise for
     * @param array            $options  Options for the request. If 'synchronous' is not
     *                                   specified, it will default to false (recommended).
     * @param Promise          $deferred The issued Promise to resolve with the API response
     *
     * @return void
     */
    protected function dispatch(RequestInterface $request, array $options, Promise $deferred): void
    {
        $options['synchronous'] ??= false;
        $clientPromise = $this->client->sendAsync($request, $options);
        $id = spl_object_id($clientPromise);
        $this->activePromises[$id] = $clientPromise;

        $clientPromise->then(
            function (ResponseInterface $response) use ($id, $deferred): void {
                unset($this->activePromises[$id]);
                $deferred->resolve($response);
                $this->advance();
            },
            function (Throwable $reason) use ($id, $deferred): void {
                unset($this->activePromises[$id]);
                $deferred->reject($reason);
                $this->advance();
            }
        );
    }

    /**
     * Blocks until all active and queued requests within the pool are resolved
     *
     * @return void
     */
    public function wait(): void
    {
        while (!empty($this->activePromises) || !empty($this->queue)) {
            $this->waitOnNextPromise();
            $this->advance();
        }
    }

    /**
     * Get the oldest active promise and wait on it. If there are no active
     * promises, then this method does nothing.
     *
     * @return void
     */
    private function waitOnNextPromise(): void
    {
        if (!empty($this->activePromises)) {
            $promise = reset($this->activePromises);
            try {
                $promise->wait();
            } catch (Throwable) {
                // We don't catch anything here; let a request's ->then() handling deal with it
            }
        }
    }
}
