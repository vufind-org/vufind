<?php

/**
 * "Get Save Statuses" AJAX handler
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
use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;

use function is_array;

/**
 * "Get Save Statuses" AJAX handler
 *
 * Check one or more records to see if they are saved in one of the user's list.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSaveStatuses extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param SessionSettings              $ss                  Session settings
     * @param ?UserEntityInterface         $user                Logged in user (or null)
     * @param Url                          $urlHelper           URL helper
     * @param UserResourceServiceInterface $userResourceService User resource database service
     */
    public function __construct(
        SessionSettings $ss,
        protected ?UserEntityInterface $user,
        protected Url $urlHelper,
        protected UserResourceServiceInterface $userResourceService
    ) {
        $this->sessionSettings = $ss;
    }

    /**
     * Format UserResourceEntityInterface object into array.
     *
     * @param UserResourceEntityInterface $data UserResourceEntityInterface object
     *
     * @return array
     */
    protected function formatListData(UserResourceEntityInterface $data): array
    {
        $list = $data->getUserList();
        return !$list ? [] : [
            'list_url' =>
                $this->urlHelper->fromRoute('userList', ['id' => $list->getId()]),
            'list_title' => $list->getTitle(),
        ];
    }

    /**
     * Obtain status data from the current logged-in user.
     *
     * @param array $ids     IDs to retrieve
     * @param array $sources Source data for IDs (parallel-indexed)
     *
     * @return array
     */
    protected function getDataFromUser($ids, $sources)
    {
        $result = $checked = [];
        foreach ($ids as $i => $id) {
            $source = $sources[$i] ?? DEFAULT_SEARCH_BACKEND;
            $selector = $source . '|' . $id;

            // We don't want to bother checking the same ID more than once, so
            // use the $checked flag array to avoid duplicates:
            if (!isset($checked[$selector])) {
                $checked[$selector] = true;

                $data = $this->userResourceService->getFavoritesForRecord($id, $source, null, $this->user);
                $result[$selector] = array_filter(array_map([$this, 'formatListData'], $data));
            }
        }
        return $result;
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
        // check if user is logged in
        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        // loop through each ID check if it is saved to any of the user's lists
        $ids = $params->fromPost('id', $params->fromQuery('id', []));
        $sources = $params->fromPost('source', $params->fromQuery('source', []));
        if (!is_array($ids) || !is_array($sources)) {
            return $this->formatResponse(
                $this->translate('Argument must be array.'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $statuses = $this->getDataFromUser($ids, $sources);
        return $this->formatResponse(compact('statuses'));
    }
}
