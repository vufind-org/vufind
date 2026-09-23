<?php

/**
 * Delete tags action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFindAdmin\Action\AdminTags;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

use function count;
use function intval;
use function is_array;

/**
 * Delete tags action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteAction extends AbstractTagsAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Delete tags.
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
        $origin = $this->getPostOrQueryParam('origin');
        $action = 'list' === $origin ? 'List' : 'Manage';

        $queryParams = 'List' === $action
            ? [
                'user_id' => $this->getPostOrQueryParam('user_id'),
                'resource_id' => $this->getPostOrQueryParam('resource_id'),
                'tag_id' => $this->getPostOrQueryParam('tag_id'),
            ] : [];

        $originUrl = $this->routeHelper->getUrlFromRoute('admin/tags', compact('action'), $queryParams);
        $newUrl = $this->routeHelper->getUrlFromRoute('admin/tags', ['action' => 'Delete']);
        $confirm = (bool)$this->getPostParam('confirm', '0');

        // Delete All
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if ('manage' === $origin || null !== $this->getPostOrQueryParam('deleteFilter')) {
            if (false === $confirm) {
                return $this->confirmTagsDeleteByFilter($originUrl, $newUrl);
            }
            $delete = $this->deleteResourceTagsByFilter();
        } else {
            // Delete by ID
            // Fail if we have nothing to delete:
            $ids = null === $this->getPostParam('deletePage')
                ? $this->getPostParam('ids')
                : $this->getPostParam('idsAll');

            if (!is_array($ids) || empty($ids)) {
                $flashMessagesHelper->addErrorMessage('bulk_noitems_advice');
                return $redirectHelper->redirectToUrl($response, $originUrl);
            }

            if (false === $confirm) {
                return $this->confirmTagsDelete($ids, $originUrl, $newUrl);
            }
            $delete = $this->resourceTagsService->deleteLinksByResourceTagsIdArray($ids);
        }

        if (0 == $delete) {
            $flashMessagesHelper->addErrorMessage('tags_delete_fail');
            return $redirectHelper->redirectToUrl($response, $originUrl);
        }

        // If we got this far, we should clean up orphans:
        $this->tagDbService->deleteOrphanedTags();

        $flashMessagesHelper->addSuccessMessage(
            [
                'msg' => 'tags_deleted',
                'tokens' => ['%count%' => $delete],
            ]
        );
        return $redirectHelper->redirectToUrl($response, $originUrl);
    }

    /**
     * Get confirmation messages.
     *
     * @param int $count Count of tags that are about to be deleted
     *
     * @return array
     */
    protected function getConfirmDeleteMessages(int $count): array
    {
        // Default all messages to "All"; we'll make them more specific as needed:
        $userMsg = $tagMsg = $resourceMsg = $this->translate('All');

        $userId = intval($this->getPostOrQueryParam('user_id'));
        if ($userId) {
            if (!($user = $this->userService->getUserById($userId))) {
                throw new \Exception("Unexpected error retrieving user $userId");
            }
            $userMsg = "{$user->getUsername()} ({$user->getId()})";
        }

        $tagId = intval($this->getPostOrQueryParam('tag_id'));
        if ($tagId) {
            if (!($tag = $this->tagDbService->getTagById($tagId))) {
                throw new \Exception("Unexpected error retrieving tag $tagId");
            }
            $tagMsg = "{$tag->getTag()} ({$tag->getId()})";
        }

        $resourceId = intval($this->getPostOrQueryParam('resource_id'));
        if ($resourceId) {
            if (!($resource = $this->resourceService->getResourceById($resourceId))) {
                throw new \Exception("Unexpected error retrieving resource $resourceId");
            }
            $title = $resource->getDisplayTitle() ?? $resource->getTitle();
            $resourceMsg = "$title ({$resource->getId()})";
        }

        $messages = [
            [
                'msg' => 'tag_delete_warning',
                'tokens' => ['%count%' => $count],
            ],
        ];
        if ($userId || $tagId || $resourceId) {
            $messages[] = [
                'msg' => 'tag_delete_filter',
                'tokens' => [
                    '%username%' => $userMsg,
                    '%tag%' => $tagMsg,
                    '%resource%' => $resourceMsg,
                ],
            ];
        }
        $messages[] = ['msg' => 'confirm_delete'];
        return $messages;
    }

    /**
     * Confirm deletion of tags by an ID list.
     *
     * @param array  $ids       A list of resource tag Ids
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return mixed
     */
    protected function confirmTagsDelete($ids, $originUrl, $newUrl)
    {
        $count = count($ids);

        return $this->getHelper(ForwardHelper::class)->forwardToConfirm(
            $this->request,
            $this->response,
            'confirm_delete_tags_brief',
            $newUrl,
            $originUrl,
            $this->getConfirmDeleteMessages($count),
            [
                'origin' => 'list',
                'user_id' => $this->getPostOrQueryParam('user_id'),
                'tag_id' => $this->getPostOrQueryParam('tag_id'),
                'resource_id' => $this->getPostOrQueryParam('resource_id'),
                'ids' => $ids,
            ]
        );
    }

    /**
     * Confirm deletion of tags by a filter.
     *
     * @param string $originUrl An origin url
     * @param string $newUrl    The url of the desired action
     *
     * @return mixed
     */
    protected function confirmTagsDeleteByFilter($originUrl, $newUrl)
    {
        $count = $this->tagsService->getResourceTagsPaginator(
            $this->convertFilter($this->getPostOrQueryParam('user_id')),
            $this->convertFilter($this->getPostOrQueryParam('resource_id')),
            $this->convertFilter($this->getPostOrQueryParam('tag_id'))
        )->getTotalItemCount();

        return $this->getHelper(ForwardHelper::class)->forwardToConfirm(
            $this->request,
            $this->response,
            'confirm_delete_tags_brief',
            $newUrl,
            $originUrl,
            $this->getConfirmDeleteMessages($count),
            [
                'origin' => 'manage',
                'type' => $this->getPostOrQueryParam('type'),
                'user_id' => $this->getPostOrQueryParam('user_id'),
                'tag_id' => $this->getPostOrQueryParam('tag_id'),
                'resource_id' => $this->getPostOrQueryParam('resource_id'),
                'deleteFilter' => $this->getPostOrQueryParam('deleteFilter'),
            ]
        );
    }

    /**
     * Delete tags based on filter settings.
     *
     * @return int Number of IDs deleted
     */
    protected function deleteResourceTagsByFilter(): int
    {
        return $this->resourceTagsService->deleteResourceTags(
            $this->convertFilter($this->getPostOrQueryParam('user_id')),
            $this->convertFilter($this->getPostOrQueryParam('resource_id')),
            $this->convertFilter($this->getPostOrQueryParam('tag_id'))
        );
    }
}
