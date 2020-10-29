<?php
/**
 * Finna HTTP client.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace Finna\Http;

use Laminas\Uri\Http;

/**
 * Finna HTTP client.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class Client extends \Laminas\Http\Client
{
    /**
     * Separating this from send method allows subclasses to wrap
     * the interaction with the adapter
     *
     * Finna: Optionally log requests
     *
     * @param Http   $uri     URI
     * @param string $method  HTTP method
     * @param bool   $secure  Whether the request is secure
     * @param array  $headers Request headers
     * @param string $body    Request body
     *
     * @return string the raw response
     * @throws Exception\RuntimeException
     */
    protected function doRequest(Http $uri, $method, $secure = false, $headers = [],
        $body = ''
    ) {
        if ($traceFile = ($this->config['tracefile'] ?? '')) {
            $backtrace = debug_backtrace(
                DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS,
                min($this->config['tracefilestackdepth'] ?? 10, 30)
            );
            $callers = [];
            foreach ($backtrace as $current) {
                $file = $current['file'] ?? '[no file]';
                $function = $current['function'] ?? '[no function]';
                $line = $current['line'] ?? '[no line]';
                $object = $current['object'] ?? null;
                $callers[] = $object ? (get_class($object) . "->$function:$line")
                    : "$file:$line";
            }
            $logStr = date('Y-m-d H:i:s') . "\t" . $uri->getHost() . "\t$method\t"
                . $uri->toString() . "\t" . implode(' << ', $callers)
                . "\t" . ($_SERVER['HTTP_HOST'] ?? '-')
                . ($_SERVER['REQUEST_URI'] ?? '-')
                . "\n";
            file_put_contents($traceFile, $logStr, FILE_APPEND | LOCK_EX);
        }
        return parent::doRequest($uri, $method, $secure, $headers, $body);
    }
}
