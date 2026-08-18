<?php

/**
 * Notices home action.
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\Notices;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Notices home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractNoticeAction
{
    /**
     * List notices.
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
        $noticeList = $this->noticeManager->getAdminList();
        foreach ($noticeList as &$notice) {
            $restrictions = $this->getDateTimeRestrictions($notice);
            if (isset($restrictions['start_date_time']) || isset($restrictions['end_date_time'])) {
                $notice['start'] = $restrictions['start_date_time'] ?? null;
                $notice['end'] = $restrictions['end_date_time'] ?? null;
                $notice['dateTimeType'] = 'date_time';
                $notice['dateTimeFormat'] = 'Y-m-d H:i:s';
                $notice['dateTimeDisplayFunction'] = 'convertToDisplayDateAndTime';
            } elseif (isset($restrictions['start_date']) || isset($restrictions['end_date'])) {
                $notice['start'] = $restrictions['start_date'] ?? null;
                $notice['end'] = $restrictions['end_date'] ?? null;
                $notice['dateTimeType'] = 'date';
                $notice['dateTimeFormat'] = 'Y-m-d';
                $notice['dateTimeDisplayFunction'] = 'convertToDisplayDate';
            } elseif (isset($restrictions['start_time']) || isset($restrictions['end_time'])) {
                $notice['start'] = $restrictions['start_time'] ?? null;
                $notice['end'] = $restrictions['end_time'] ?? null;
                $notice['dateTimeType'] = 'time';
                $notice['dateTimeFormat'] = 'H:i:s';
                $notice['dateTimeDisplayFunction'] = 'convertToDisplayTime';
            }
        }
        return $this->renderTemplate($request, $response, compact('noticeList'), 'admin/notices/home');
    }
}
