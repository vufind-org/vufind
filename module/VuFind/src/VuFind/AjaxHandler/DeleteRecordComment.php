<?php

/**
 * AJAX handler to delete a comment on a record.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018-2024.
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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * AJAX handler to delete a comment on a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DeleteRecordComment extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param CommentsServiceInterface $commentsService Comments database service
     * @param ?UserEntityInterface     $user            Logged in user (or null)
     * @param bool                     $enabled         Are comments enabled?
     */
    public function __construct(
        protected CommentsServiceInterface $commentsService,
        protected ?UserEntityInterface $user,
        protected bool $enabled = true
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
        // Make sure comments are enabled:
        if (!$this->enabled) {
            return $this->formatResponse(
                $this->translate('Comments disabled'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $params->fromQuery('id');
        if (empty($id)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (!$this->commentsService->deleteIfOwnedByUser($id, $this->user)) {
            return $this->formatResponse(
                $this->translate('edit_list_fail'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        return $this->formatResponse('');
    }
}
