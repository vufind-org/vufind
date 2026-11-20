<?php

/**
 * Edit custom link action.
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

namespace VuFindAdmin\Action\AdminCustomLinks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Edit custom link action.
 *
 * @category VuFind
 * @package  Action
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EditAction extends AbstractCustomLinkAction
{
    /**
     * Edit custom link.
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
            return $this->returnToCustomLinksAdminHome();
        }

        $customLink = $this->getCustomLinkByQueryParam();

        $formData = $this->getFormData($customLink);

        if (!$this->isPost()) {
            return $this->renderTemplate(
                $request,
                $response,
                compact('formData'),
                'admin/customlinks/edit'
            );
        }

        $type = $this->getPostParam('type_fieldset')['type'] ?? null;

        if ($pageHrid = $customLink['page'] ?? null) {
            $this->contentPageManager->editDatabaseContentPageByHrid(
                $pageHrid,
                $this->formDataToContentPage()
            );
        } elseif ($type === 'page') {
            $pageHrid = $this->contentPageManager->addContentPageToDatabaseAndGetHrid(
                $this->formDataToContentPage()
            );
        }

        $this->customLinkManager->editDatabaseCustomLink(
            $customLink['id'],
            $this->formDataToCustomLink($pageHrid)
        );

        return $this->returnToCustomLinksAdminHome();
    }
}
