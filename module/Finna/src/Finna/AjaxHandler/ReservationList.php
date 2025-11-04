<?php

/**
 * Reservation list ajax handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  AjaxHandler
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace Finna\AjaxHandler;

use Finna\ReservationList\Handler\PluginManager;
use Finna\ReservationList\ReservationListService;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Reservation list ajax handler
 *
 * @category VuFind
 * @package  AjaxHandler
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class ReservationList extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface   $user                   Logged in user (or null)
     * @param ReservationListService $reservationListService Reservation list service
     * @param PluginManager          $connectionHandler      Reservation List connection handler
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected ReservationListService $reservationListService,
        protected PluginManager $connectionHandler,
    ) {
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
        if ($this->user === null) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }
        $listId = (int)$params->fromQuery('list_id');
        if (!$listId) {
            return $this->formatResponse(
                'Bad request',
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $list = $this->reservationListService->getListById($listId, $this->user);
        if (!$list) {
            return $this->formatResponse(
                'Bad request',
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $result = [];
        $type = $params->fromQuery('type');
        if ('status' === $type) {
            $listHandler = $this->reservationListService->getListHandler(
                $list->getInstitution(),
                $list->getListConfigIdentifier()
            );
            $response = $listHandler->getListStatus($list, $this->user);
            $result['status'] = $this->translate($response);
        } else {
            return $this->formatResponse('Bad request', self::STATUS_HTTP_BAD_REQUEST);
        }
        return $this->formatResponse($result);
    }
}
