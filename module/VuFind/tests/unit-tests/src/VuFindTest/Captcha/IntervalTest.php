<?php

/**
 * Unit tests for Interval captcha.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Captcha;

/**
 * Unit tests for Image CAPTCHA handler factory.
 *
 * @requires extension gd
 * @requires function imagepng
 * @requires function imageftbbox
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class IntervalTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test Interval captchas
     *
     * @return void
     */
    public function testIntervalCaptcha(): void
    {
        $session = new \Laminas\Session\Container('SessionState');
        $config = new \Laminas\Config\Config(
            [
                'Captcha' => [
                    'time_from_session_start' => 20,
                ],
            ]
        );

        $interval = new \VuFind\Captcha\Interval($session, $config);
        $params = $this->createMock(\Laminas\Mvc\Controller\Plugin\Params::class);

        // Check that first check passes if session data has not been initialized:
        $this->assertTrue($interval->verify($params));
        $this->assertFalse($interval->verify($params));
        $this->assertEquals(
            'interval_captcha_not_passed',
            $interval->getErrorMessage()
        );

        // Check pass with session start time:
        $session->sessionStartTime = time() - 20;
        $session->lastProtectedActionTime = null;
        $this->assertTrue($interval->verify($params));

        // Check fail with session start time but new action too soon:
        $session->lastProtectedActionTime = time() - 20;
        $this->assertFalse($interval->verify($params));

        // Check pass with long enough interval:
        $session->lastProtectedActionTime = time() - 60;
        $this->assertTrue($interval->verify($params));
    }
}
