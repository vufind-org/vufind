<?php
/**
 * Logger Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Log;
use VuFind\Log\Logger;

/**
 * Sitemap Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class LoggerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test logException()
     *
     * @return void
     */
    public function testLogException()
    {
        $callback = function ($a) {
            $expectedContext = <<<CONTEXT
Server Context:
Array
(
    [REMOTE_ADDR] => 1.2.3.4
    [HTTP_USER_AGENT] => Fake browser
    [REQUEST_URI] => /foo/bar
)
CONTEXT;
            return $a[1] === 'test'
                && $a[2] === 'test(Server: IP = 1.2.3.4, Referer = none, User Agent = Fake browser, Request URI = /foo/bar)'
                && false !== strpos($a[3], $a[2])
                && false !== strpos($a[3], 'Backtrace:')
                && false !== strpos($a[3], 'line')
                && false !== strpos($a[3], 'class =')
                && false !== strpos($a[3], 'function =')
                && false !== strpos($a[4], $expectedContext)
                && false !== strpos($a[4], 'Backtrace:')
                && false !== strpos($a[4], 'line')
                && false !== strpos($a[4], 'class =')
                && false !== strpos($a[4], 'function =')
                && false !== strpos($a[5], $expectedContext)
                && false !== strpos($a[5], 'Backtrace:')
                && false !== strpos($a[5], 'line')
                && false !== strpos($a[5], 'args:')
                && false !== strpos($a[5], 'class =')
                && false !== strpos($a[5], 'function =')
                && count($a) == 5;
        };
        $logger = $this->getMock('VuFind\Log\Logger', ['log']);
        $logger->expects($this->once())->method('log')->with($this->equalTo(Logger::CRIT), $this->callback($callback));
        try {
            throw new \Exception('test');
        } catch (\Exception $e) {
            $fakeServer = new \Zend\Stdlib\Parameters(
                [
                    'REMOTE_ADDR' => '1.2.3.4',
                    'HTTP_USER_AGENT' => 'Fake browser',
                    'REQUEST_URI' => '/foo/bar'
                ]
            );
            $logger->logException($e, $fakeServer);
        }
    }
}