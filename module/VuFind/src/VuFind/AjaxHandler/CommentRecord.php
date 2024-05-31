<?php

/**
 * AJAX handler to comment on a record.
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
use VuFind\Config\AccountCapabilities;
use VuFind\Controller\Plugin\Captcha;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Ratings\RatingsService;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;

use function intval;

/**
 * AJAX handler to comment on a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CommentRecord extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ResourcePopulator        $resourcePopulator   Resource populator service
     * @param CommentsServiceInterface $commentsService     Comments database service
     * @param Captcha                  $captcha             Captcha controller plugin
     * @param ?UserEntityInterface     $user                Logged in user (or null)
     * @param bool                     $enabled             Are comments enabled?
     * @param RecordLoader             $recordLoader        Record loader
     * @param AccountCapabilities      $accountCapabilities Account capabilities helper
     * @param RatingsService           $ratingsService      Ratings service
     */
    public function __construct(
        protected ResourcePopulator $resourcePopulator,
        protected CommentsServiceInterface $commentsService,
        protected Captcha $captcha,
        protected ?UserEntityInterface $user,
        protected bool $enabled,
        protected RecordLoader $recordLoader,
        protected AccountCapabilities $accountCapabilities,
        protected RatingsService $ratingsService
    ) {
    }

    /**
     * Is CAPTCHA valid? (Also returns true if CAPTCHA is disabled).
     *
     * @return bool
     */
    protected function checkCaptcha()
    {
        // Not enabled? Report success!
        if (!$this->captcha->active('userComments')) {
            return true;
        }
        $this->captcha->setErrorMode('none');
        return $this->captcha->verify();
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
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $params->fromPost('id');
        $source = $params->fromPost('source', DEFAULT_SEARCH_BACKEND);
        $comment = $params->fromPost('comment');
        if (empty($id) || empty($comment)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $driver = $this->recordLoader->load($id, $source, false);

        if (!$this->checkCaptcha()) {
            return $this->formatResponse(
                $this->translate('captcha_not_passed'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        $resource = $this->resourcePopulator->getOrCreateResourceForRecordId($id, $source);
        $commentId = $this->commentsService->addComment(
            $comment,
            $this->user,
            $resource
        );

        $rating = $params->fromPost('rating', '');
        if (
            $driver->isRatingAllowed()
            && ('' !== $rating
            || $this->accountCapabilities->isRatingRemovalAllowed())
        ) {
            $this->ratingsService->saveRating(
                $driver,
                $this->user->getId(),
                '' === $rating ? null : intval($rating)
            );
        }

        return $this->formatResponse(compact('commentId'));
    }
}
