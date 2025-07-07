<?php

/**
 * Config Upgrade Test Class
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\ConfigManager;
use VuFind\Config\PathResolver;
use VuFind\Config\Upgrade;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

use function in_array;

/**
 * Config Upgrade Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UpgradeTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Target upgrade version
     *
     * @var string
     */
    protected string $targetVersion = '11.0';

    /**
     * Get an upgrade object for the specified source version:
     *
     * @param string $fixture Fixture
     *
     * @return Upgrade
     */
    protected function getUpgrader(string $fixture): Upgrade
    {
        $container = $this->getContainerWithConfigRelatedServices(
            localDir: $this->getFixtureDir() . 'configs/' . $fixture,
            localSubDir: ''
        );
        return new Upgrade(
            $container->get(PathResolver::class),
            $container->get(ConfigManager::class),
        );
    }

    /**
     * Run config upgrader with fixture.
     *
     * @param string $fixture Fixture
     *
     * @return Upgrade
     */
    protected function runAndGetConfigUpgrader(string $fixture): Upgrade
    {
        $upgrader = $this->getUpgrader($fixture);
        $upgrader->setWriteMode(false);
        $upgrader->run($this->targetVersion);
        return $upgrader;
    }

    /**
     * Data provider for testDatabaseUpgrade().
     *
     * @return array[]
     */
    public static function databaseUpgradeProvider(): array
    {
        return [
            'legacy and new formats' => [
                'database-both-formats',
                // New format should take precedence:
                [
                    'use_ssl' => '',
                    'verify_server_certificate' => '',
                    'database_driver' => 'mysql',
                    'database_username' => 'notroot',
                    'database_password' => 'password',
                    'database_host' => 'localhost',
                    'database_port' => '3306',
                    'database_name' => 'vufind',
                ],
            ],
            'legacy format only' => [
                'database-legacy-format',
                [
                    'use_ssl' => '',
                    'verify_server_certificate' => '',
                    'database' => 'mysql://user:pass@localhost/vufind_custom',
                ],
            ],
            'new format only' => [
                'database-new-format',
                [
                    'use_ssl' => '',
                    'verify_server_certificate' => '',
                    'database_driver' => 'mysql',
                    'database_username' => 'notroot',
                    'database_password' => 'password',
                    'database_host' => 'localhost',
                    'database_port' => '3306',
                    'database_name' => 'vufind',
                ],
            ],
            'new format only, with file-based password' => [
                'database-new-format-password-file',
                [
                    'use_ssl' => '',
                    'verify_server_certificate' => '',
                    'database_driver' => 'mysql',
                    'database_username' => 'notroot',
                    'database_password_file' => '/path/to/secret',
                    'database_host' => 'localhost',
                    'database_port' => '3306',
                    'database_name' => 'vufind',
                ],
            ],
        ];
    }

    /**
     * Test database upgrade in config.ini
     *
     * @param string $fixture  Fixture file
     * @param array  $expected Expected result
     *
     * @return void
     *
     * @dataProvider databaseUpgradeProvider
     */
    public function testDatabaseUpgrade(string $fixture, array $expected): void
    {
        $upgrader = $this->runAndGetConfigUpgrader($fixture);
        $results = $upgrader->getNewConfigs();
        $this->assertEquals($expected, $results['config.ini']['Database']);
    }

    /**
     * Test generator upgrade.
     *
     * @return void
     */
    public function testDefaultGenerator(): void
    {
        // We expect the upgrader to switch default values:
        $upgrader = $this->runAndGetConfigUpgrader('defaultgenerator');
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'VuFind ' . $this->targetVersion,
            $results['config.ini']['Site']['generator']
        );

        // We expect the upgrader not to change custom values:
        $upgrader = $this->runAndGetConfigUpgrader('customgenerator');
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'Custom Generator',
            $results['config.ini']['Site']['generator']
        );
    }

    /**
     * Test spellchecker changes.
     *
     * @return void
     */
    public function testSpelling(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('spelling');
        $results = $upgrader->getNewConfigs();

        // Make sure spellcheck 'simple' is replaced by 'dictionaries'
        $this->assertFalse(isset($results['config.ini']['Spelling']['simple']));
        $this->assertTrue(isset($results['config.ini']['Spelling']['dictionaries']));
    }

    /**
     * Test Syndetics upgrade.
     *
     * @return void
     */
    public function testSyndetics(): void
    {
        // Test upgrading an SSL URL
        $upgrader = $this->runAndGetConfigUpgrader('syndeticsurlssl');
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            1,
            $results['config.ini']['Syndetics']['use_ssl']
        );

        // Test upgrading a non-SSL URL
        $upgrader = $this->runAndGetConfigUpgrader('syndeticsurlnossl');
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            '',
            $results['config.ini']['Syndetics']['use_ssl']
        );
    }

    /**
     * Test Google preview setting upgrade
     *
     * @return void
     */
    public function testGooglePreviewUpgrade(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('googlepreview');
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'noview,full',
            $results['config.ini']['Content']['GoogleOptions']['link']
        );
    }

    /**
     * Test permission upgrade
     *
     * @return void
     */
    public function testPermissionUpgrade(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('permissions');
        $results = $upgrader->getNewConfigs();

        // Admin assertions:
        $this->assertFalse(isset($results['config.ini']['AdminAuth']));
        $adminConfig = [
            'ipRegEx' => '/1\.2\.3\.4|1\.2\.3\.5/',
            'username' => ['username1', 'username2'],
            'permission' => 'access.AdminModule',
        ];
        $this->assertEquals(
            $adminConfig,
            $results['permissions.ini']['access.AdminModule']
        );

        // Summon assertions
        $this->assertFalse(isset($results['Summon.ini']['Auth']));
        $summonConfig = [
            'role' => ['loggedin'],
            'ipRegEx' => '/1\.2\.3\.4|1\.2\.3\.5/',
            'boolean' => 'OR',
            'permission' => 'access.SummonExtendedResults',
        ];
        $this->assertEquals(
            $summonConfig,
            $results['permissions.ini']['access.SummonExtendedResults']
        );

        // EIT assertions:
        $eitConfig = ['role' => 'loggedin', 'permission' => 'access.EITModule'];
        $this->assertEquals(
            $eitConfig,
            $results['permissions.ini']['default.EITModule']
        );

        // Primo assertions:
        $this->assertFalse(isset($results['Primo.ini']['Institutions']['code']));
        $this->assertFalse(isset($results['Primo.ini']['Institutions']['regex']));
        $this->assertEquals(
            'DEFAULT',
            $results['Primo.ini']['Institutions']['defaultCode']
        );
        $expectedRegex = [
            'MEMBER1' => '/^1\.2\..*/',
            'MEMBER2' => ['/^2\.3\..*/', '/^3\.4\..*/'],
        ];
        foreach ($expectedRegex as $code => $regex) {
            $perm = "access.PrimoInstitution.$code";
            $this->assertEquals(
                $perm,
                $results['Primo.ini']['Institutions']["onCampusRule['$code']"]
            );
            $permDetails = [
                'ipRegEx' => $regex,
                'permission' => $perm,
            ];
            $this->assertEquals($permDetails, $results['permissions.ini'][$perm]);
        }
    }

    /**
     * Test Google-related warnings.
     *
     * @return void
     */
    public function testGoogleWarnings(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('googlewarnings');
        $warnings = $upgrader->getWarnings();
        $this->assertTrue(
            in_array(
                'The [GoogleSearch] section of config.ini is no '
                . 'longer supported due to changes in Google APIs.',
                $warnings
            )
        );
        $this->assertTrue(
            in_array(
                'The [GoogleAnalytics] universal setting is off. See config.ini '
                . 'for important information on how to upgrade your Analytics.',
                $warnings
            )
        );
        $this->assertTrue(
            in_array(
                'Google Maps is no longer a supported Content/recordMap option;'
                . ' please review your config.ini.',
                $warnings
            )
        );
        $results = $upgrader->getNewConfigs();
        $this->assertFalse(isset($results['config.ini']['Content']['recordMap']));
        $this->assertFalse(
            isset($results['config.ini']['Content']['googleMapApiKey'])
        );
    }

    /**
     * Test WorldCat-related warnings.
     *
     * @return void
     */
    public function testWorldCatWarnings(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('worldcatwarnings');
        $warnings = $upgrader->getWarnings();
        $this->assertTrue(
            in_array(
                'The [WorldCat] section of config.ini has been removed following'
                . ' the shutdown of the v1 WorldCat search API; use WorldCat2.ini instead.',
                $warnings
            )
        );
    }

    /**
     * Test comment extraction.
     *
     * @return void
     */
    public function testCommentExtraction(): void
    {
        $upgrader = $this->getUpgrader('comments');
        $config = $this->getFixtureDir() . 'configs/comments/config.ini';
        $this->assertEquals(
            [
                'sections' => [
                    'Section' => [
                        'before' => "; This is a top comment\n",
                        'inline' => '',
                        'settings' => [
                            'foo' => [
                                'before' => "; This is a setting comment\n",
                                'inline' => '',
                            ],
                            'bar' => [
                                'before' => "\n",
                                'inline' => '; this is an inline comment',
                            ],
                        ],
                    ],
                    'NextSection' => [
                        'before' => "\n",
                        'inline' => '; this is an inline section comment',
                        'settings' => [],
                    ],
                ],
                'after' => "\n; This is a trailing comment",
            ],
            $this->callMethod($upgrader, 'extractComments', [$config])
        );
    }

    /**
     * Data provider for testEbscoUpgrades
     *
     * @return array
     */
    public static function ebscoUpgradeProvider(): array
    {
        return [
            [
                'eds',
                'EDS.ini',
            ],
            [
                'epf',
                'EPF.ini',
            ],
        ];
    }

    /**
     * Test EDS and EPF upgrades.
     *
     * @param string $backend        Name of the backend
     * @param string $configFilename Configuration filename, EDS.ini or EPF.ini
     *
     * @return void
     *
     * @dataProvider ebscoUpgradeProvider
     */
    public function testEbscoUpgrade(string $backend, string $configFilename): void
    {
        $upgrader = $this->runAndGetConfigUpgrader($backend);
        $this->assertEquals([], $upgrader->getWarnings());
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            ['foo' => 'bar'],
            $results[$configFilename]['Facets']
        );
        $this->assertEquals(
            'list_test',
            $results[$configFilename]['General']['default_view']
        );
    }

    /**
     * Test Primo upgrade.
     *
     * @return void
     */
    public function testPrimoUpgrade(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('primo');
        $this->assertEquals([], $upgrader->getWarnings());
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'http://my-id.hosted.exlibrisgroup.com:1701',
            $results['Primo.ini']['General']['url']
        );
    }

    /**
     * Test deprecated Amazon cover content warning.
     *
     * @return void
     */
    public function testAmazonCoverWarning(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('amazoncover');
        $warnings = $upgrader->getWarnings();
        $this->assertTrue(
            in_array(
                'WARNING: You have Amazon content enabled, but VuFind no longer sup'
                . 'ports it. You should remove Amazon references from config.ini.',
                $warnings
            )
        );
    }

    /**
     * Test deprecated Amazon review content warning.
     *
     * @return void
     */
    public function testAmazonReviewWarning(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('amazonreview');
        $warnings = $upgrader->getWarnings();
        $this->assertTrue(
            in_array(
                'WARNING: You have Amazon content enabled, but VuFind no longer sup'
                . 'ports it. You should remove Amazon references from config.ini.',
                $warnings
            )
        );
    }

    /**
     * Test ReCaptcha setting migration.
     *
     * @return void
     */
    public function testReCaptcha(): void
    {
        $upgrader = $this->runAndGetConfigUpgrader('recaptcha');
        $results = $upgrader->getNewConfigs();
        $captcha = $results['config.ini']['Captcha'];
        $this->assertEquals('public', $captcha['recaptcha_siteKey']);
        $this->assertEquals('private', $captcha['recaptcha_secretKey']);
        $this->assertEquals('theme', $captcha['recaptcha_theme']);
        $this->assertEquals(['recaptcha'], $captcha['types']);
    }

    /**
     * Data provider for testMailRequireLoginMigration().
     *
     * @return array[]
     */
    public static function mailRequireLoginProvider(): array
    {
        return [
            'false' => ['email-require-login-false', 'enabled'],
            'true' => ['email-require-login-true', 'require_login'],
        ];
    }

    /**
     * Test migration of [Mail] require_login setting.
     *
     * @param string $fixture  Fixture to load
     * @param string $expected Expected migrated setting
     *
     * @return void
     *
     * @dataProvider mailRequireLoginProvider
     */
    public function testMailRequireLoginMigration(string $fixture, string $expected): void
    {
        $upgrader = $this->runAndGetConfigUpgrader($fixture);
        $results = $upgrader->getNewConfigs();
        $this->assertFalse(isset($results['config.ini']['Mail']['require_login']));
        $this->assertEquals($expected, $results['config.ini']['Mail']['email_action']);
    }
}
