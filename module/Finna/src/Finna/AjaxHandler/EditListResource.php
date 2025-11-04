<?php

/**
 * AJAX handler for editing a list resource.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\View\Helper\Root\Markdown;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AJAX handler for editing a list resource.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EditListResource extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface         $user                Logged in user (or null)
     * @param UserResourceServiceInterface $userResourceService UserResource database service
     * @param Markdown                     $markdownHelper      Markdown view helper
     * @param bool                         $enabled             Are lists enabled?
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected UserResourceServiceInterface $userResourceService,
        protected Markdown $markdownHelper,
        protected $enabled = true
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
        // Fail if lists are disabled:
        if (!$this->enabled) {
            return $this->formatResponse(
                $this->translate('Lists disabled'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (null === $this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $listParams = $params->fromPost('params');
        if (
            !isset($listParams['listId']) || !isset($listParams['notes'])
            || !isset($listParams['id'])
        ) {
            return $this->formatResponse(
                $this->translate('Missing parameter'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (!empty($listParams['source'])) {
            $source = $listParams['source'];
        } else {
            $map = ['pci' => 'Primo', 'eds' => 'Eds', 'summon' => 'Summon'];
            [$source] = explode('.', $listParams['id'], 2);
            $source = $map[$source] ?? DEFAULT_SEARCH_BACKEND;
        }

        $listId = $listParams['listId'];
        $notes = $listParams['notes'];

        $userResources = $this->userResourceService
            ->getFavoritesForRecord($listParams['id'], $source, $listId, $this->user);
        if (empty($userResources)) {
            return $this->formatResponse(
                'User resource not found',
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        foreach ($userResources as $userResource) {
            $this->userResourceService->createOrUpdateLink(
                $userResource->getResource(),
                $userResource->getUser(),
                $userResource->getUserList(),
                $notes
            );
        }

        $response = [];
        if (!empty($notes) && null !== $this->markdownHelper) {
            $response['notesHtml'] = $this->markdownHelper->toHtml($notes);
        }

        return $this->formatResponse($response);
    }
}
