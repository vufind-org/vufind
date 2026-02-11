<?php

/**
 * Short link redirect action
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019-2025.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\ShortLink;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Action\Helper\PluginManager as HelperPluginManager;
use VuFind\UrlShortener\UrlShortenerInterface;
use VuFind\View\Renderer\TemplateRendererInterface;

use function is_callable;
use function strlen;

/**
 * Short link redirect action
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RedirectAction extends AbstractTemplateRenderingAction
{
    /**
     * Amount of seconds after which HTML redirect is performed.
     *
     * @var int
     */
    protected $redirectDelayHtml = 3;

    /**
     * Constructor
     *
     * @param HelperPluginManager       $helperPluginManager Helper plugin manager
     * @param TemplateRendererInterface $templateRenderer    Template renderer
     * @param UrLShortenerInterface     $shortener           URL shortener
     * @param string                    $redirectMethod      Redirect mechanism to use
     * (html, http, threshold:<urlLength>)
     */
    public function __construct(
        HelperPluginManager $helperPluginManager,
        TemplateRendererInterface $templateRenderer,
        protected UrlShortenerInterface $shortener,
        protected string $redirectMethod
    ) {
        parent::__construct($helperPluginManager, $templateRenderer);
    }

    /**
     * Redirect to given URL by using a HTML meta redirect mechanism.
     *
     * @param string $url Redirect target
     *
     * @return ResponseInterface
     */
    protected function redirectViaHtml(string $url): ResponseInterface
    {
        return $this->templateRenderer->renderTemplate(
            $this->request,
            $this->response,
            ['redirectTarget' => $url, 'redirectDelay' => $this->redirectDelayHtml]
        );
    }

    /**
     * Redirect to given URL by using a HTTP header.
     *
     * @param string $url Redirect target
     *
     * @return ResponseInterface
     */
    protected function redirectViaHttp($url): ResponseInterface
    {
        return $this->response->withStatus(302)
            ->withHeader('Location', $url);
    }

    /**
     * Resolve full version of shortlink & redirect to target.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if ($id = $request->getAttribute('id')) {
            if ($url = $this->shortener->resolve($id)) {
                $threshRegEx = '"^threshold:(\d+)$"i';
                if (preg_match($threshRegEx, $this->redirectMethod, $hits)) {
                    $threshold = $hits[1];
                    $method = (strlen($url) > $threshold) ? 'Html' : 'Http';
                } else {
                    $method = ucwords($this->redirectMethod);
                }
                if (!is_callable([$this, 'redirectVia' . $method])) {
                    throw new \VuFind\Exception\BadConfig(
                        'Invalid redirect method: ' . $method
                    );
                }
                return $this->{'redirectVia' . $method}($url);
            }
        }
        return $this->templateRenderer->renderNotFoundPage($request, $response);
    }
}
