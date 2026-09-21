<?php

/**
 * Feedback home action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\AdminFeedback;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Db\Service\FeedbackServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

use function intval;

/**
 * Feedback home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractAdminAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer         $globalsContainer Globals container
     * @param array                    $config           VuFind configuration
     * @param FeedbackServiceInterface $feedbackService  Feedback database service
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected FeedbackServiceInterface $feedbackService,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Display feedback home page.
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
        $feedback = $this->feedbackService->getFeedbackPaginator(
            $this->convertFilter($this->getPostOrQueryParam('form_name')),
            $this->convertFilter($this->getPostOrQueryParam('site_url')),
            $this->convertFilter($this->getPostOrQueryParam('status')),
            (int)($this->getPostOrQueryParam('page', default: '1'))
        );
        $templateParams = [
            'feedback' => $feedback,
            'statuses' => $this->getStatuses(),
            'uniqueForms' => $this->feedbackService->getUniqueColumn('form_name'),
            'uniqueSites' => $this->feedbackService->getUniqueColumn('site_url'),
            'params' => $request->getQueryParams() + $request->getParsedBody(),
        ];
        return $this->renderTemplate($request, $response, $templateParams, 'admin/feedback/home');
    }

    /**
     * Converts null and "ALL" params to null.
     *
     * @param ?string $value A parameter to check
     *
     * @return ?string A modified parameter
     */
    protected function convertFilter(?string $value): ?string
    {
        return 'ALL' === $value ? null : $value;
    }

    /**
     * Get available feedback statuses.
     *
     * @return array
     */
    protected function getStatuses(): array
    {
        return [
            'open',
            'in progress',
            'pending',
            'answered',
            'closed',
        ];
    }
}
