<?php

/**
 * Unit tests for DevTools Language action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2014.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  DevTools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Controller;

use GuzzleHttp\Psr7\Response;
use Laminas\I18n\Translator\TextDomain;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\I18n\Locale\LocaleSettings;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindDevTools\Action\DevTools\LanguageAction;
use VuFindTest\Action\AbstractActionTestCase;

use function count;

/**
 * Unit tests for DevTools Language action.
 *
 * @category VuFind
 * @package  DevTools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LanguageTest extends AbstractActionTestCase
{
    /**
     * Test language action.
     *
     * @return void
     */
    public function testLanguageAction()
    {
        $localeSettings = $this->createMock(LocaleSettings::class);
        $localeSettings->expects($this->once())->method('getEnabledLocales')
            ->willReturn(['en' => 'English']);
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('renderTemplate')
            ->willReturnCallback(
                /**
                 * Mock renderer.
                 *
                 * @param ServerRequestInterface $request        Request
                 * @param ResponseInterface      $response       Response object
                 * @param ?string                $template       Template name, or null to use default for the action
                 * @param array                  $params         Template parameters
                 * @param array[]                $childTemplates Any child templates; an array of associative array with
                 * keys 'template' and 'params'
                 *
                 * @return ResponseInterface
                 *
                 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
                 */
                function (
                    ServerRequestInterface $request,
                    ResponseInterface $response,
                    ?string $template = null,
                    array $params = [],
                    array $childTemplates = [],
                ): ResponseInterface {
                    $response->getBody()->write(serialize($params));
                    return $response;
                }
            );
        $action = new LanguageAction($localeSettings);
        $this->initializeAction($action, renderer: $renderer);

        $response = $action($this->getServerRequest(), new Response());
        $body = $response->getBody();
        $body->rewind();
        $result = unserialize($body->getContents());

        // Test default language selection -- English
        $this->assertEquals('en', $result['mainCode']);
        $this->assertEquals('English', $result['mainName']);

        // Make sure correct type of object was loaded:
        $this->assertInstanceOf(TextDomain::class, $result['main']);

        // Shortcut to help check some key details:
        $en = $result['details']['en'];

        // Did we load help files correctly?
        $this->assertGreaterThan(3, count($en['helpFiles']));
        $this->assertContains('search.phtml', $en['helpFiles']);

        // Did we put the object in the right place?
        $this->assertInstanceOf(TextDomain::class, $en['object']);

        // Did the @parent_ini macro get stripped correctly?
        $this->assertArrayNotHasKey('@parent_ini', $result['details']['en-gb']['object']);

        // Did the native.ini file get properly ignored?
        $this->assertArrayNotHasKey('native', $result['details']);

        // Did the aliases.ini file get properly ignored?
        $this->assertArrayNotHasKey('aliases', $result['details']);
    }
}
