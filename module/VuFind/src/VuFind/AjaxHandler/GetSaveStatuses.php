<?php
/**
 * "Get Save Statuses" AJAX handler
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\Db\Row\User;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;

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
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Constructor
     *
     * @param SessionSettings $ss        Session settings
     * @param User|bool       $user      Logged in user (or false)
     * @param Url             $urlHelper URL helper
     */
    public function __construct(SessionSettings $ss, $user, Url $urlHelper)
    {
        $this->sessionSettings = $ss;
        $this->user = $user;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Format list object into array.
     *
     * @param array $list List data
     *
     * @return array
     */
    protected function formatListData($list)
    {
        return [
            'list_url' =>
                $this->urlHelper->fromRoute('userList', ['id' => $list['list_id']]),
            'list_title' => $list['list_title'],
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

                $data = $this->user->getSavedData($id, null, $source);
                $result[$selector] = ($data && count($data) > 0)
                    ? array_map([$this, 'formatListData'], $data->toArray()) : [];
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
