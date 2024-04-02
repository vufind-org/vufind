<?php

/**
 * AJAX handler to comment on a record.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
use VuFind\Db\Row\User;
use VuFind\Db\Table\Resource;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader as RecordLoader;

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
     * @param Resource            $table               Resource database table
     * @param Captcha             $captcha             Captcha controller plugin
     * @param ?User               $user                Logged in user (or null)
     * @param bool                $enabled             Are comments enabled?
     * @param RecordLoader        $recordLoader        Record loader
     * @param AccountCapabilities $accountCapabilities Account capabilities helper
     */
    public function __construct(
        protected Resource $table,
        protected Captcha $captcha,
        protected ?User $user,
        protected bool $enabled,
        protected RecordLoader $recordLoader,
        protected AccountCapabilities $accountCapabilities
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

        $resource = $this->table->findResource($id, $source);
        $commentId = $resource->addComment($comment, $this->user);

        $rating = $params->fromPost('rating', '');
        if (
            $driver->isRatingAllowed()
            && ('' !== $rating
            || $this->accountCapabilities->isRatingRemovalAllowed())
        ) {
            $driver->addOrUpdateRating(
                $this->user->id,
                '' === $rating ? null : intval($rating)
            );
        }

        return $this->formatResponse(compact('commentId'));
    }
}
