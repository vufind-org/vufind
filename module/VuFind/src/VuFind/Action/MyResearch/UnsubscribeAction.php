<?php

/**
 * MyResearch "unsubscribe from a scheduled search" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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

namespace VuFind\Action\MyResearch;

use Laminas\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\AuditEventType;

/**
 * MyResearch "unsubscribe from a scheduled search" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UnsubscribeAction extends AbstractSearchHistoryAction
{
    /**
     * Schedule a search.
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
        $id = $this->getQueryParam('id');
        $key = $this->getQueryParam('key');
        $type = $this->getQueryParam('type', 'alert');
        if (null === $id || null === $key) {
            throw new \Exception('Missing parameters.');
        }
        $templateParams = [];
        if ($this->getQueryParam('confirm') === '1') {
            if ('alert' === $type) {
                if (!($search = $this->searchService->getSearchById($id))) {
                    throw new \Exception('Invalid parameters.');
                }
                $secret = $this->secretCalculator->getSearchUnsubscribeSecret($search);
                if ($key !== $secret) {
                    throw new \Exception('Invalid parameters.');
                }
                $search->setNotificationFrequency(0);
                $this->searchService->persistEntity($search);

                $this->auditEventService->addEvent(
                    AuditEventType::User,
                    AuditEventSubtype::SaveSearch,
                    $search->getUser(),
                    data: [
                        'search_id' => $search->getId(),
                        'notification_frequency' => 0,
                    ]
                );

                $templateParams['success'] = true;
            }
        } else {
            $templateParams['unsubscribeUrl'] = (string)$request->getUri() . '&confirm=1';
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
