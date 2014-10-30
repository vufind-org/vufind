<?php
/**
 * Config Upgrade Test Class
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
namespace VuFindTest\Config;
use VuFind\Config\Upgrade;

/**
 * Config Upgrade Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class UpgradeTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Target upgrade version
     *
     * @var string
     */
    protected $targetVersion = '2.0';

    /**
     * Get an upgrade object for the specified source version:
     *
     * @param string $version Version
     *
     * @return Upgrade
     */
    protected function getUpgrader($version)
    {
        $oldDir = realpath(__DIR__ . '/../../../../fixtures/configs/' . $version);
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

        // We should always update BulkExport options to latest full set when
        // upgrading a default configuration:
        $this->assertEquals(
            'MARC:MARCXML:EndNote:EndNoteWeb:RefWorks:BibTeX',
            $results['config.ini']['BulkExport']['options']
        );

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

        // Prior to 1.3, we expect exactly one warning about using a non-blueprint
        // theme:
        if ((float)$version < 1.3) {
            $this->assertEquals(1, count($warnings));
            $this->assertEquals(
                "WARNING: This version of VuFind does not support "
                . "the default theme.  Your config.ini [Site] theme setting "
                . "has been reset to the default: blueprint.  You may need to "
                . "reimplement your custom theme.",
                $warnings[0]
            );
        } else {
            $this->assertEquals(0, count($warnings));
        }

        // Summon should always have the checkboxes setting turned on after
        // upgrade:
        $this->assertEquals(
            'daterange,checkboxes:Summon',
            $results['Summon.ini']['Advanced_Facet_Settings']['special_facets']
        );

        // Make sure the obsolete Index/local setting is removed:
        $this->assertFalse(isset($results['config.ini']['Index']['local']));

        return array('configs' => $results, 'warnings' => $warnings);
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
     * Test "meaningful line" detection in SolrMarc properties files.
     *
     * @return void
     */
    public function testMeaningfulLineDetection()
    {
        $upgrader = $this->getUpgrader('1.4');
        $meaningless = realpath(
            __DIR__ . '/../../../../fixtures/configs/solrmarc/empty.properties'
        );
        $this->assertFalse(
            $this->callMethod(
                $upgrader, 'fileContainsMeaningfulLines', array($meaningless)
            )
        );
        $meaningful = realpath(
            __DIR__ . '/../../../../fixtures/configs/solrmarc/meaningful.properties'
        );
        $this->assertTrue(
            $this->callMethod(
                $upgrader, 'fileContainsMeaningfulLines', array($meaningful)
            )
        );
    }
}