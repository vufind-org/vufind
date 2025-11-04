<?php

/**
 * AJAX handler for editing a list.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2024.
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
use Laminas\Stdlib\Parameters;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Favorites\FavoritesService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Tags\TagsService;

/**
 * AJAX handler for editing a list.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EditList extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ?UserEntityInterface     $user             Logged in user (or null)
     * @param UserListServiceInterface $userListService  UserList database service
     * @param FavoritesService         $favoritesService Favorites service
     * @param TagsService              $tagsService      Tags service
     * @param RendererInterface        $renderer         View renderer
     * @param bool                     $enabled          Are lists enabled?
     * @param bool                     $listTagsEnabled  Are list tags enabled?
     * @param ?Markdown                $markdownHelper   Markdown view helper
     */
    public function __construct(
        protected ?UserEntityInterface $user,
        protected UserListServiceInterface $userListService,
        protected FavoritesService $favoritesService,
        protected TagsService $tagsService,
        protected RendererInterface $renderer,
        protected bool $enabled = true,
        protected bool $listTagsEnabled = false,
        protected ?Markdown $markdownHelper = null
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
        if (!isset($listParams['id']) || !isset($listParams['title'])) {
            return $this->formatResponse(
                $this->translate('Missing parameter'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        // Is this a new list or an existing list?  Handle the special 'NEW' value
        // of the ID parameter:
        $newList = 'NEW' === $listParams['id'];
        $list = $newList ? $this->favoritesService->createListForUser($this->user)
            : $this->userListService->getUserListById($listParams['id']);

        if (!$newList && !$this->favoritesService->userCanEditList($this->user, $list)) {
            throw new ListPermissionException('Access denied.');
        }

        if ($this->listTagsEnabled && isset($listParams['tags'])) {
            $tags = array_map(
                function ($tag) {
                    $tag = urldecode($tag);
                    // Quote tag with whitespace to prevent VuFind
                    // from creating multiple tags.
                    return str_contains($tag, ' ') ? "\"{$tag}\"" : $tag;
                },
                $listParams['tags']
            );
            $listParams['tags'] = implode(' ', $tags);
        } elseif (!$this->listTagsEnabled) {
            // Make sure that saved tags are preserved when tagging is disabled.
            unset($listParams['tags']);
        }

        $finalId = $this->favoritesService->updateListFromRequest($list, $this->user, new Parameters($listParams));

        $listParams['id'] = $finalId;

        if ($this->listTagsEnabled) {
            $tags = $this->tagsService->getListTags($list, $list->getUser());
            $listParams['tags-edit'] = $this->renderer->partial(
                'myresearch/mylist-tags.phtml',
                ['tags' => $tags, 'editable' => true]
            );
            $listParams['tags'] = $this->renderer->partial(
                'myresearch/mylist-tags.phtml',
                ['tags' => $tags, 'editable' => false]
            );
        } else {
            unset($listParams['tags']);
        }

        if (!empty($listParams['desc']) && null !== $this->markdownHelper) {
            $listParams['descHtml'] = $this->markdownHelper->toHtml($listParams['desc']);
        }

        return $this->formatResponse($listParams);
    }
}
