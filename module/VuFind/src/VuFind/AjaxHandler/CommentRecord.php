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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Captcha\Service\CaptchaService;
use VuFind\Config\AccountCapabilities;
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
     * Constructor.
     *
     * @param ResourcePopulator        $resourcePopulator   Resource populator service
     * @param CommentsServiceInterface $commentsService     Comments database service
     * @param CaptchaService           $captcha             Captcha service
     * @param ?UserEntityInterface     $user                Logged in user (or null)
     * @param bool                     $enabled             Are comments enabled?
     * @param RecordLoader             $recordLoader        Record loader
     * @param AccountCapabilities      $accountCapabilities Account capabilities helper
     * @param RatingsService           $ratingsService      Ratings service
     */
    public function __construct(
        protected ResourcePopulator $resourcePopulator,
        protected CommentsServiceInterface $commentsService,
        protected CaptchaService $captcha,
        protected ?UserEntityInterface $user,
        protected bool $enabled,
        protected RecordLoader $recordLoader,
        protected AccountCapabilities $accountCapabilities,
        protected RatingsService $ratingsService
    ) {
        parent::__construct(null);
    }

    /**
     * Is CAPTCHA valid? (Also returns true if CAPTCHA is disabled).
     *
     * @param ServerRequestInterface $request Request
     *
     * @return bool
     */
    protected function checkCaptcha(ServerRequestInterface $request)
    {
        // Not enabled? Report success!
        if (!$this->captcha->active('userComments')) {
            return true;
        }
        $this->captcha->setErrorMode('none');
        return $this->captcha->verify($request->getParsedBody(), $request->getQueryParams());
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

        $id = $this->getPostParam($request, 'id');
        $source = $this->getPostParam($request, 'source', DEFAULT_SEARCH_BACKEND);
        $comment = $this->getPostParam($request, 'comment');
        if (empty($id) || empty($comment)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }
        $driver = $this->recordLoader->load($id, $source, false);

        if (!$this->checkCaptcha($request)) {
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

        $rating = $this->getPostParam($request, 'rating', '');
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
