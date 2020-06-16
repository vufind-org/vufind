<?php
/**
 * AJAX handler for getting authority information for recommendations.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\Recommend\AuthorityRecommend;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;

/**
 * AJAX handler for getting authority information for recommendations.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetAuthorityFullInfo extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * AuthorityRecommend
     *
     * @var AuthorityRecommend
     */
    protected $authorityRecommend;

    /**
     * Search Results manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Search table
     *
     * @var \VuFind\Db\Table\Search
     */
    protected $searchTable;

    /**
     * Session
     *
     * @var \Laminas\Session\Container
     */
    protected $session;

    /**
     * Session manager
     *
     * @var \Laminas\Session\SessionManager
     */
    protected $sessionManager;

    /**
     * Constructor
     *
     * @param RendererInterface                    $renderer           View renderer
     * @param AuthorityRecommend                   $authorityRecommend Authority
     * Recommend
     * @param \VuFind\Search\Results\PluginManager $resultsManager     Search
     * results manager
     * @param \VuFInd\Db\Table\Search              $searchTable        Search table
     * @param \Laminas\Session\Container           $session            Session
     * @param \Laminas\Session\SessionManager      $sessionManager     Session
     * manager
     */
    public function __construct(
        RendererInterface $renderer,
        AuthorityRecommend $authorityRecommend,
        \VuFind\Search\Results\PluginManager $resultsManager,
        \VuFInd\Db\Table\Search $searchTable,
        \Laminas\Session\Container $session,
        \Laminas\Session\SessionManager $sessionManager
    ) {
        $this->renderer = $renderer;
        $this->authorityRecommend = $authorityRecommend;
        $this->resultsManager = $resultsManager;
        $this->searchTable = $searchTable;
        $this->session = $session;
        $this->sessionManager = $sessionManager;
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
        $id = $params->fromQuery('id');
        $searchId = $params->fromQuery('searchId');

        if (!$id || !$searchId) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $sessId = $this->sessionManager->getId();
        $search = $this->searchTable->getOwnedRowById($searchId, $sessId, null);
        if (empty($search)) {
            return $this->formatResponse(
                'Search not found', self::STATUS_HTTP_BAD_REQUEST
            );
        }

        $minSO = $search->getSearchObject();
        $savedSearch = $minSO->deminify($this->resultsManager);
        $searchParams = $savedSearch->getParams();

        $this->authorityRecommend
            ->init($searchParams, $params->getController()->getRequest());
        $this->authorityRecommend->process($savedSearch);
        $recommendations = $this->authorityRecommend->getRecommendations();

        $authority = end($recommendations);
        foreach ($recommendations as $rec) {
            if ($rec->getUniqueID() === $id) {
                $authority = $rec;
                break;
            }
        }

        // Save active author ID and active authority filters
        $this->session->activeId = $id;
        $this->session->idsWithRoles = $searchParams->getAuthorIdFilter(true);

        $html = $this->renderer->partial(
            'ajax/authority-recommend.phtml',
            ['recommend' => $this->authorityRecommend, 'authority' => $authority]
        );

        return $this->formatResponse(compact('html'));
    }
}
