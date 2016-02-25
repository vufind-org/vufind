<?php
/**
 * Mink link resolver test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;

/**
 * Mink link resolver test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class LinkResolverTest extends \VuFindTest\Unit\MinkTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @return array
     */
    public function getConfigIniOverrides()
    {
        return [
            'OpenURL' => [
                'resolver' => 'demo',
                'embed' => '1',
                'url' => 'https://vufind.org/wiki',
            ]
        ];
    }

    public function testPlaceHold()
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
            ]
        );

        // Search for a known record:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCss($page, '.searchForm [name="lookfor"]')
            ->setValue('id:testsample1');
        $this->findCss($page, '.btn.btn-primary')->click();

        // Click the OpenURL link:
        $this->findCss($page, '.fulltext')->click();
        $this->snooze();

        // Confirm that the expected fake demo driver data is there:
        $electronic = $this->findCss($page, 'a.access-open');
        $this->assertEquals('Electronic', $electronic->getText());
        $this->assertEquals('Electronic fake2', $electronic->getParent()->getText());
        $openUrl = 'url_ver=Z39.88-2004&ctx_ver=Z39.88-2004'
            . '&ctx_enc=info%3Aofi%2Fenc%3AUTF-8'
            . '&rfr_id=info%3Asid%2Fvufind.svn.sourceforge.net%3Agenerator'
            . '&rft.title=Journal+of+rational+emotive+therapy+%3A+the+journal+'
            . 'of+the+Institute+for+Rational-Emotive+Therapy.'
            . '&rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&rft.creator='
            . '&rft.pub=The+Institute%2C&rft.format=Journal'
            . '&rft.language=English&rft.issn=0748-1985';
        $this->assertEquals(
            'https://vufind.org/wiki?' . $openUrl . '#electronic',
            $electronic->getAttribute('href')
        );

        $print = $this->findCss($page, 'a.access-unknown');
        $this->assertEquals('Print', $print->getText());
        $this->assertEquals('Print fake1', $print->getParent()->getText());
        $this->assertEquals(
            'https://vufind.org/wiki?' . $openUrl . '#print',
            $print->getAttribute('href')
        );
    }
}
