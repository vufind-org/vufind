<?php

/**
 * AJAX handler to edit notices.
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
 * @package  AJAX
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindAdmin\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\AjaxHandler\AbstractBase;
use VuFind\Content\NoticeManager;
use VuFind\Session\Settings as SessionSettings;

/**
 * AJAX handler to edit notices.
 *
 * This will check the ILS for being online and will return the ils-offline
 * template upon failure.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EditNotices extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param SessionSettings $ss            Session settings
     * @param NoticeManager   $noticeManager Notice Manager
     */
    public function __construct(
        SessionSettings $ss,
        protected NoticeManager $noticeManager
    ) {
        parent::__construct($ss);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $notices = $this->getPostParam($request, 'notices');

        foreach ($notices as $noticeId => $noticeData) {
            if (isset($noticeData['enabled'])) {
                $noticeData['enabled'] = ($noticeData['enabled'] === 'true');
            }
            $this->noticeManager->editDatabaseNotice($noticeId, $noticeData);
        }

        return $this->formatResponse(['']);
    }
}
