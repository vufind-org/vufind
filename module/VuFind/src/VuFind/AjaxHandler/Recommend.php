<?php

/**
 * Load a recommendation module via AJAX.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Stdlib\Parameters;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Recommend\PluginManager as RecommendManager;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Helper\Root\Recommend as RecommendHelper;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Load a recommendation module via AJAX.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recommend extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param SessionSettings           $sessionSettings        Session settings
     * @param RecommendManager          $recommendPluginManager Recommendation plugin manager
     * @param Results                   $results                Solr results object
     * @param TemplateRendererInterface $renderer               Template renderer,
     * @param RecommendHelper           $recommendHelper        Recommend view helper
     */
    public function __construct(
        SessionSettings $sessionSettings,
        protected RecommendManager $recommendPluginManager,
        protected Results $results,
        protected TemplateRendererInterface $renderer,
        protected RecommendHelper $recommendHelper,
    ) {
        parent::__construct($sessionSettings);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Process recommendations -- for now, we assume Solr-based search objects,
        // since deferred recommendations work best for modules that don't care about
        // the details of the search objects anyway:
        if (!($moduleName = $this->getQueryParam($request, 'mod'))) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $module = $this->recommendPluginManager->get($moduleName);
        $module->setConfig($this->getQueryParam($request, 'params', ''));
        $paramsObj = $this->results->getParams();
        $request = new Parameters($request->getQueryParams());
        // Initialize search parameters from Ajax request parameters in case the
        // original request parameters were passed to the Ajax request.
        $paramsObj->initFromRequest($request);
        $module->init($paramsObj, $request);
        $module->process($this->results);

        // Render recommendations:
        return $this->formatResponse(($this->recommendHelper)($module));
    }
}
