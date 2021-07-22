<?php
/**
 * "Get Resolver Links" AJAX handler
 *
 * PHP version 7
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
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace TueFind\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Resolver\Connection;
use VuFind\Resolver\Driver\PluginManager as ResolverManager;
use VuFind\Session\Settings as SessionSettings;


class GetSubscriptionBundleEntries extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    protected $searchResultsManager;

    public function __construct(\VuFind\Search\Results\PluginManager $searchResultsManager) {
        $this->searchResultsManager = $searchResultsManager;
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
        $bundle_id = $params->fromQuery('bundle_id');
        $results = $this->searchResultsManager->get('Solr');
        $params = $results->getParams();
        $params->getOptions()->spellcheckEnabled(false);
        $params->getOptions()->disableHighlighting();
        $params->setOverrideQuery('bundle_id:' . str_replace(':', '\\:', $bundle_id));
        $results->setParams($params);
        $result_set = $results->getResults();
        $titles = [];
        foreach ($result_set as $record)
            $titles[] = ['id' => $record->getUniqueID(), 'title' => $record->getTitle()];
        return $this->formatResponse($titles);
    }
}
