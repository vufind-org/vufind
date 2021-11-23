<?php
/**
 * Config Upgrade Test Class
 *
 * PHP version 7
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

use VuFind\Config\Upgrade;

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
class UpgradeTest extends \VuFindTest\Unit\TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

    /**
     * Target upgrade version
     *
     * @var string
     */
    protected $targetVersion = '2.4';

    /**
     * Get an upgrade object for the specified source version:
     *
     * @param string $version Version
     *
     * @return Upgrade
     */
    protected function getUpgrader($version)
    {
        $oldDir = realpath($this->getFixtureDir() . 'configs/' . $version);
        $rawDir = realpath(__DIR__ . '/../../../../../../../config/vufind');
        return new Upgrade($version, $this->targetVersion, $oldDir, $rawDir);
    }

    /**
     * Perform standard tests for the specified version and return resulting configs
     * and warnings so that further assertions can be performed by calling code if
     * necessary.
     *
     * @return array
     */
    protected function checkVersion($version)
    {
        $upgrader = $this->getUpgrader($version);
        $upgrader->run();
        $results = $upgrader->getNewConfigs();

        // Prior to 1.4, Advanced should always == HomePage after upgrade:
        if ((float)$version < 1.4) {
            $this->assertEquals(
                print_r($results['facets.ini']['Advanced'], true),
                print_r($results['facets.ini']['HomePage'], true)
            );
        }

        // SMS configuration should contain general and carriers sections:
        $this->assertTrue(isset($results['sms.ini']['General']));
        $this->assertTrue(isset($results['sms.ini']['Carriers']));
        $warnings = $upgrader->getWarnings();

        // Prior to 2.4, we expect exactly one warning about using a deprecated
        // theme:
        $expectedWarnings = [
            'The Statistics module has been removed from Vufind. '
            . 'For usage tracking, please configure Google Analytics or Piwik.'
        ];
        if ((float)$version < 1.3) {
            $expectedWarnings[] = "WARNING: This version of VuFind does not support "
                . "the default theme. Your config.ini [Site] theme setting "
                . "has been reset to the default: bootprint3. You may need to "
                . "reimplement your custom theme.";
        } elseif ((float)$version < 2.4) {
            $expectedWarnings[] = "WARNING: This version of VuFind does not support "
                . "the blueprint theme. Your config.ini [Site] theme setting "
                . "has been reset to the default: bootprint3. You may need to "
                . "reimplement your custom theme.";
        }
        $this->assertEquals($expectedWarnings, $warnings);

        // Summon should always have the checkboxes setting turned on after
        // upgrade:
        $this->assertEquals(
            'daterange,checkboxes:Summon',
            $results['Summon.ini']['Advanced_Facet_Settings']['special_facets']
        );

        // Make sure the obsolete Index/local setting is removed:
        $this->assertFalse(isset($results['config.ini']['Index']['local']));

        // Make sure that spelling recommendations are set up appropriately:
        $this->assertEquals(
            ['TopFacets:ResultsTop', 'SpellingSuggestions'],
            $results['searches.ini']['General']['default_top_recommend']
        );
        $this->assertTrue(
            in_array(
                'SpellingSuggestions',
                $results['searches.ini']['General']['default_noresults_recommend']
            )
        );
        $this->assertEquals(
            [
                'Author' => ['AuthorFacets', 'SpellingSuggestions'],
                'CallNumber' => ['TopFacets:ResultsTop'],
                'WorkKeys' => ['']
            ],
            $results['searches.ini']['TopRecommendations']
        );
        $this->assertEquals(
            ['CallNumber' => 'callnumber-sort'],
            $results['searches.ini']['DefaultSortingByType']
        );
        $this->assertEquals(
            'sort_callnumber',
            $results['searches.ini']['Sorting']['callnumber-sort']
        );
        $this->assertEquals(
            ['SummonDatabases', 'SpellingSuggestions'],
            $results['Summon.ini']['General']['default_top_recommend']
        );
        $this->assertTrue(
            in_array(
                'SpellingSuggestions',
                $results['Summon.ini']['General']['default_noresults_recommend']
            )
        );
        $this->assertEquals(
            [],
            $results['Summon.ini']['TopRecommendations']
        );

        // Confirm that author facets have been upgraded appropriately.
        $this->assertFalse(isset($results['facets.ini']['Results']['authorStr']));
        $this->assertFalse(isset($results['Collection.ini']['Facets']['authorStr']));
        $this->assertEquals(
            'Author', $results['facets.ini']['Results']['author_facet']
        );
        $this->assertEquals(
            'author_facet', $results['facets.ini']['LegacyFields']['authorStr']
        );
        // Collection.ini only exists after release 1.3:
        if ((float)$version > 1.3) {
            $this->assertEquals(
                'Author', $results['Collection.ini']['Facets']['author_facet']
            );
        }
        // verify expected order of facet fields
        $this->assertEquals(
            [
                'institution', 'building', 'format', 'callnumber-first',
                'author_facet', 'language', 'genre_facet', 'era_facet',
                'geographic_facet', 'publishDate'
            ],
            array_keys($results['facets.ini']['Results'])
        );

        return ['configs' => $results, 'warnings' => $warnings];
    }

    /**
     * Test upgrading from 1.1.
     *
     * @return void
     */
    public function testUpgrade11()
    {
        $this->checkVersion('1.1');
    }

    /**
     * Test upgrading from 1.2.
     *
     * @return void
     */
    public function testUpgrade12()
    {
        $this->checkVersion('1.2');
    }

    /**
     * Test upgrading from 1.3.
     *
     * @return void
     */
    public function testUpgrade13()
    {
        $this->checkVersion('1.3');
    }

    /**
     * Test upgrading from 1.4.
     *
     * @return void
     */
    public function testUpgrade14()
    {
        $this->checkVersion('1.4');
    }

    /**
     * Test generator upgrade.
     *
     * @return void
     */
    public function testDefaultGenerator()
    {
        // We expect the upgrader to switch default values:
        $upgrader = $this->getUpgrader('defaultgenerator');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'VuFind ' . $this->targetVersion,
            $results['config.ini']['Site']['generator']
        );

        // We expect the upgrader not to change custom values:
        $upgrader = $this->getUpgrader('customgenerator');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'Custom Generator', $results['config.ini']['Site']['generator']
        );
    }

    /**
     * Test Syndetics upgrade.
     *
     * @return void
     */
    public function testSyndetics()
    {
        // Test upgrading an SSL URL
        $upgrader = $this->getUpgrader('syndeticsurlssl');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            1, $results['config.ini']['Syndetics']['use_ssl']
        );

        // Test upgrading a non-SSL URL
        $upgrader = $this->getUpgrader('syndeticsurlnossl');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            '', $results['config.ini']['Syndetics']['use_ssl']
        );
    }

    /**
     * Test Google preview setting upgrade
     *
     * @return void
     */
    public function testGooglePreviewUpgrade()
    {
        $upgrader = $this->getUpgrader('googlepreview');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'noview,full', $results['config.ini']['Content']['GoogleOptions']['link']
        );
    }

    /**
     * Test removal of xID settings
     *
     * @return void
     */
    public function testXidDeprecation()
    {
        $upgrader = $this->getUpgrader('xid');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            ['Similar'], $results['config.ini']['Record']['related']
        );
        $this->assertEquals(
            ['WorldCatSimilar'], $results['WorldCat.ini']['Record']['related']
        );
        $this->assertEquals(['apiKey' => 'foo'], $results['config.ini']['WorldCat']);
        $expectedWarnings = [
            'The [WorldCat] id setting is no longer used and has been removed.',
            'The [WorldCat] xISBN_token setting is no longer used and has been removed.',
            'The [WorldCat] xISBN_secret setting is no longer used and has been removed.',
            'The [WorldCat] xISSN_token setting is no longer used and has been removed.',
            'The [WorldCat] xISSN_secret setting is no longer used and has been removed.',
            'The Editions related record module is no longer supported due to OCLC\'s xID API shutdown. It has been removed from your settings.',
            'The WorldCatEditions related record module is no longer supported due to OCLC\'s xID API shutdown. It has been removed from your settings.',
        ];
        $this->assertEquals($expectedWarnings, $upgrader->getWarnings());
    }

    /**
     * Test permission upgrade
     *
     * @return void
     */
    public function testPermissionUpgrade()
    {
        $upgrader = $this->getUpgrader('permissions');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();

        // Admin assertions:
        $this->assertFalse(isset($results['config.ini']['AdminAuth']));
        $adminConfig = [
            'ipRegEx' => '/1\.2\.3\.4|1\.2\.3\.5/',
            'username' => ['username1', 'username2'],
            'permission' => 'access.AdminModule'
        ];
        $this->assertEquals(
            $adminConfig, $results['permissions.ini']['access.AdminModule']
        );

        // Summon assertions
        $this->assertFalse(isset($results['Summon.ini']['Auth']));
        $summonConfig = [
            'role' => ['loggedin'],
            'ipRegEx' => '/1\.2\.3\.4|1\.2\.3\.5/',
            'boolean' => 'OR',
            'permission' => 'access.SummonExtendedResults'
        ];
        $this->assertEquals(
            $summonConfig,
            $results['permissions.ini']['access.SummonExtendedResults']
        );

        // EIT assertions:
        $eitConfig = ['role' => 'loggedin', 'permission' => 'access.EITModule'];
        $this->assertEquals(
            $eitConfig, $results['permissions.ini']['default.EITModule']
        );

        // Primo assertions:
        $this->assertFalse(isset($results['Primo.ini']['Institutions']['code']));
        $this->assertFalse(isset($results['Primo.ini']['Institutions']['regex']));
        $this->assertEquals(
            'DEFAULT', $results['Primo.ini']['Institutions']['defaultCode']
        );
        $expectedRegex = [
            'MEMBER1' => '/^1\.2\..*/',
            'MEMBER2' => ['/^2\.3\..*/', '/^3\.4\..*/']
        ];
        foreach ($expectedRegex as $code => $regex) {
            $perm = "access.PrimoInstitution.$code";
            $this->assertEquals(
                $perm, $results['Primo.ini']['Institutions']["onCampusRule['$code']"]
            );
            $permDetails = [
                'ipRegEx' => $regex,
                'permission' => $perm
            ];
            $this->assertEquals($permDetails, $results['permissions.ini'][$perm]);
        }
    }

    /**
     * Test Google-related warnings.
     *
     * @return void
     */
    public function testGoogleWarnings()
    {
        $upgrader = $this->getUpgrader('googlewarnings');
        $upgrader->run();
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
    public function testWorldCatWarnings()
    {
        $upgrader = $this->getUpgrader('worldcatwarnings');
        $upgrader->run();
        $warnings = $upgrader->getWarnings();
        $this->assertTrue(
            in_array(
                'The [WorldCat] LimitCodes setting never had any effect and has been'
                . ' removed.',
                $warnings
            )
        );
    }

    /**
     * Test WorldCat-specific upgrades.
     *
     * @return void
     */
    public function testWorldCatUpgrades()
    {
        $upgrader = $this->getUpgrader('worldcatupgrades');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $this->assertEquals(
            'Author',
            $results['WorldCat.ini']['Basic_Searches']['srw.au']
        );
        $this->assertEquals(
            'adv_search_author',
            $results['WorldCat.ini']['Advanced_Searches']['srw.au']
        );
    }

    /**
     * Test "meaningful line" detection in SolrMarc properties files.
     *
     * @return void
     */
    public function testMeaningfulLineDetection()
    {
        $upgrader = $this->getUpgrader('1.4');
        $meaningless = realpath(
            $this->getFixtureDir() . 'configs/solrmarc/empty.properties'
        );
        $this->assertFalse(
            $this->callMethod(
                $upgrader, 'fileContainsMeaningfulLines', [$meaningless]
            )
        );
        $meaningful = realpath(
            $this->getFixtureDir() . 'configs/solrmarc/meaningful.properties'
        );
        $this->assertTrue(
            $this->callMethod(
                $upgrader, 'fileContainsMeaningfulLines', [$meaningful]
            )
        );
    }

    /**
     * Test Primo upgrade.
     *
     * @return void
     */
    public function testPrimoUpgrade()
    {
        $upgrader = $this->getUpgrader('primo');
        $upgrader->run();
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
    public function testAmazonCoverWarning()
    {
        $upgrader = $this->getUpgrader('amazoncover');
        $upgrader->run();
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
    public function testAmazonReviewWarning()
    {
        $upgrader = $this->getUpgrader('amazonreview');
        $upgrader->run();
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
    public function testReCaptcha()
    {
        $upgrader = $this->getUpgrader('recaptcha');
        $upgrader->run();
        $results = $upgrader->getNewConfigs();
        $captcha = $results['config.ini']['Captcha'];
        $this->assertEquals('public', $captcha['recaptcha_siteKey']);
        $this->assertEquals('private', $captcha['recaptcha_secretKey']);
        $this->assertEquals('theme', $captcha['recaptcha_theme']);
        $this->assertEquals(['recaptcha'], $captcha['types']);
    }
}
