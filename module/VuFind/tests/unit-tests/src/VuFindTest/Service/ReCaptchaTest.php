<?php

/**
 * ReCaptcha Service Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Service;

use Laminas\ReCaptcha\ReCaptcha as LaminasReCaptcha;
use VuFind\Service\ReCaptcha;

/**
 * CurrencyFormatter Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ReCaptchaTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get constructor parameters to set up a test service.
     *
     * @return array
     */
    protected function getTestParams(): array
    {
        $options = ['theme' => 'dark'];
        $client = $this->createMock(\Laminas\Http\Client::class);
        return ['sitekey', 'secretkey', ['ssl' => true], $options, null, $client];
    }

    /**
     * Test that our service strips script tags from the Laminas service.
     *
     * @return void
     */
    public function testScriptStripping(): void
    {
        $args = $this->getTestParams();
        $laminas = new LaminasReCaptcha(...$args);
        $ours = new ReCaptcha(...$args);
        $this->assertStringContainsString('<script', $laminas->getHtml());
        $this->assertStringNotContainsString('<script', $ours->getHtml());
    }

    /**
     * Test that our service methods from the Laminas class.
     *
     * @return void
     */
    public function testProxying(): void
    {
        $service = new ReCaptcha(...$this->getTestParams());
        $expectedOptions = [
            'theme' => 'dark', // we overrode this, the rest are defaults
            'type' => 'image',
            'size' => 'normal',
            'tabindex' => 0,
            'callback' => null,
            'expired-callback' => null,
            'hl' => null,
        ];
        $this->assertEquals($expectedOptions, $service->getOptions());
    }

    /**
     * Test unsupported method proxying.
     *
     * @return void
     */
    public function testUnsupportedMethodProxying(): void
    {
        $this->expectExceptionMessage('Unsupported method: notSupportedMethod');
        $service = new ReCaptcha(...$this->getTestParams());
        $service->notSupportedMethod('This does not work!');
    }
}
