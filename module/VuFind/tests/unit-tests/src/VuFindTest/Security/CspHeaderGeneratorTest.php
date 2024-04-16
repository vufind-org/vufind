<?php

/**
 * CspHeaderGenerator test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Security;

use VuFind\Security\CspHeaderGenerator;

/**
 * CspHeaderGenerator test
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CspHeaderGeneratorTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Nonce generator mock
     *
     * @type \PHPUnit\Framework\MockObject
     */
    protected $nonceGenerator;

    /**
     * Set up the tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->nonceGenerator = $this->createMock(\VuFind\Security\NonceGenerator::class);
    }

    /**
     * Test a basic ReportTo header configuration
     *
     * @return void
     */
    public function testReportToHeaderSimple(): void
    {
        $configData = parse_ini_file(
            $this->getFixtureDir() . 'configs/contentsecuritypolicy/contentsecuritypolicy.ini',
            true
        );
        $generator = $this->buildGenerator($configData);

        $header = $generator->getReportToHeader();
        $expectedHeader =
            '{"group":"CSPReportingEndpoint","max_age":"12345","endpoints":[{"url":"https://abc.report-uri.com"}]}';
        $this->assertEquals($expectedHeader, $header->getFieldValue());
    }

    /**
     * Test a ReportTo header configuration with two endpoints and three urls
     *
     * @return void
     */
    public function testReportToHeaderComplex(): void
    {
        $configData = parse_ini_file(
            $this->getFixtureDir() . 'configs/contentsecuritypolicy/contentsecuritypolicy.ini',
            true
        );
        $configData['ReportTo']['groups'][] = 'Endpoint2';
        $configData['ReportToEndpoint2'] = [
            'max_age' => '67890',
            'endpoints_url' => [
                'https://url1.endpoint2.com',
                'https://url2.endpoint2.com',
            ],
        ];
        $generator = $this->buildGenerator($configData);

        $header = $generator->getReportToHeader();
        // phpcs:disable Generic.Files.LineLength
        $expectedHeader =
            '{"group":"CSPReportingEndpoint","max_age":"12345","endpoints":[{"url":"https://abc.report-uri.com"}]}, ' .
            '{"group":"Endpoint2","max_age":"67890","endpoints":[{"url":"https://url1.endpoint2.com"},{"url":"https://url2.endpoint2.com"}]}';
        // phpcs:enable
        $this->assertEquals($expectedHeader, $header->getFieldValue());
    }

    /**
     * Build the CspHeaderGenerator object
     *
     * @param array $configData The contentsecuritypolicy.ini config data as an array
     *
     * @return CspHeaderGenerator
     */
    protected function buildGenerator($configData)
    {
        $config = new \Laminas\Config\Config($configData);
        $generator = new CspHeaderGenerator($config, $this->nonceGenerator);
        return $generator;
    }
}
