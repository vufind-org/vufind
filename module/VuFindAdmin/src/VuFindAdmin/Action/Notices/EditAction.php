<?php

/**
 * Edit notice action.
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
use VuFind\Exception\BadRequest;
use VuFind\Exception\NotFound;

/**
 * Edit notice action.
 *
 * @category VuFind
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditAction extends AbstractNoticeAction
{
    /**
     * Edit notice.
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
        if ($this->getPostParam('cancel') !== null) {
            return $this->returnToNoticesAdminHome();
        }

        $noticeId = $this->getQueryParam('notice_id');
        if (!$noticeId) {
            throw new BadRequest('Query parameter "notice_id" is missing.');
        }

        $notice = $this->noticeManager->getById($noticeId);
        if ($notice === null) {
            throw new NotFound('Notice does not exist');
        }

        $formData = $this->getFormData($notice);

        if (!$this->isPost()) {
            return $this->renderTemplate(
                $request,
                $response,
                compact('noticeId', 'formData'),
                'admin/notices/edit'
            );
        }

        $this->noticeManager->editNotice(
            $noticeId,
            $this->formDataToNotice()
        );

        return $this->returnToNoticesAdminHome();
    }
}
