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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use Laminas\View\Renderer\RendererInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Recommend\PluginManager as RecommendManager;
use VuFind\Search\Solr\Results;
use VuFind\Session\Settings as SessionSettings;

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
     * Recommendation plugin manager
     *
     * @var RecommendManager
     */
    protected $pluginManager;

    /**
     * Solr search results object
     *
     * @var Results
     */
    protected $results;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param RecommendManager  $pm       Recommendation plugin manager
     * @param Results           $results  Solr results object
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(
        SessionSettings $ss,
        RecommendManager $pm,
        Results $results,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->pluginManager = $pm;
        $this->results = $results;
        $this->renderer = $renderer;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Process recommendations -- for now, we assume Solr-based search objects,
        // since deferred recommendations work best for modules that don't care about
        // the details of the search objects anyway:
        if (!($moduleName = $params->fromQuery('mod'))) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $module = $this->pluginManager->get($moduleName);
        $module->setConfig($params->fromQuery('params', ''));
        $paramsObj = $this->results->getParams();
        $request = new Parameters($params->fromQuery());
        // Initialize search parameters from Ajax request parameters in case the
        // original request parameters were passed to the Ajax request.
        $paramsObj->initFromRequest($request);
        $module->init($paramsObj, $request);
        $module->process($this->results);

        // Render recommendations:
        $recommend = $this->renderer->plugin('recommend');
        return $this->formatResponse($recommend($module));
    }
}
