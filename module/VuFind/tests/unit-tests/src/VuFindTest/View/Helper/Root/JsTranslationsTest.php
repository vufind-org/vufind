<?php

/**
 * JsTranslations view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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

namespace VuFindTest\View\Helper\Root;

use VuFind\View\Helper\Root\JsTranslations;
use VuFind\View\Helper\Root\TransEsc;
use VuFind\View\Helper\Root\Translate;
use VuFindTest\Feature\TranslatorTrait;
use VuFindTest\Feature\ViewTrait;

/**
 * JsTranslations view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class JsTranslationsTest extends \PHPUnit\Framework\TestCase
{
    use ViewTrait;
    use TranslatorTrait;

    /**
     * Test JS translations.
     *
     * @return void
     */
    public function testHelper()
    {
        $view = $this->getPhpRenderer($this->getViewHelpers());
        $transEsc = new TransEsc();
        $transEsc->setView($view);
        $helper = new JsTranslations($view->plugin('translate'), $transEsc);
        $helper->setView($view);

        // Normal addStrings:
        $helper->addStrings(['1key' => 'key1']);
        $helper->addStrings(['1key_html' => 'key1']);
        $helper->addStrings(['2key' => 'key2']);
        $helper->addStrings(['key_html' => 'key_html']);
        $helper->addStrings(['keyhtml' => 'key_html']);
        $helper->addStrings(['key_unescaped' => 'key1']);
        $helper->addStrings(['tokenized' => ['%%foo%%', ['%%foo%%' => 'bar']]]);
        $expected = json_encode(
            [
                '1key' => 'Translation 1&lt;p&gt;',
                '1key_html' => 'Translation 1<p>',
                '2key' => 'Translation 2',
                'key_html' => '<span>translation</span>',
                'keyhtml' => '&lt;span&gt;translation&lt;/span&gt;',
                'key_unescaped' => 'Translation 1<p>',
                'tokenized' => 'bar',
            ]
        );
        $this->assertJsonStringEqualsJsonString($expected, $helper->getJSON());

        // Stateless:
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                [
                    '1key' => 'Translation 1&lt;p&gt;',
                    '2key' => '&lt;span&gt;translation&lt;/span&gt;',
                    '2key_html' => '<span>translation</span>',
                ]
            ),
            $helper->getJSONFromArray(
                [
                    '1key' => 'key1',
                    '2key' => 'key_html',
                    '2key_html' => 'key_html',
                ]
            )
        );

        // Verify that state hasn't changed:
        $this->assertJsonStringEqualsJsonString($expected, $helper->getJSON());
    }

    /**
     * Get view helpers needed by test.
     *
     * @return array
     */
    protected function getViewHelpers()
    {
        $translator = $this->getMockTranslator(
            [
                'default' => [
                    'key1' => 'Translation 1<p>',
                    'key_html' => '<span>translation</span>',
                    'key2' => 'Translation 2',
                ],
            ]
        );
        $translate = new Translate();
        $translate->setTranslator($translator);
        return [
            'translate' => $translate,
        ];
    }
}
