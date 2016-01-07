<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Service
 */

namespace ZendServiceTest\Amazon\S3;

use ZendService\Amazon\S3\S3;

/**
 * @category   Zend
 * @package    Zend_Service_Amazon_S3
 * @subpackage UnitTests
 * @group      Zend_Service
 * @group      Zend_Service_Amazon
 * @group      Zend_Service_Amazon_S3
 */
class OfflineTest extends \PHPUnit_Framework_TestCase
{
    public function testThrottle()
    {
        $s3 = new S3();
        $throttleTime = 0.001;  // seconds
        $limit = 5;

        $throttler = function () use ($s3, $throttleTime) {
            return $s3->throttle('microtime', array(true), $throttleTime);
        };

        $times = array_map($throttler, range(0, $limit));

        $diffs = array_map(
            function ($a, $b) { return $a - $b; },
            array_slice($times, 1, count($times)),
            array_slice($times, 0, count($times) - 1)
        );

        array_map(
            array($this, 'assertGreaterThanOrEqual'),
            array_fill(0, $limit, $throttleTime),
            $diffs
        );
    }
}
