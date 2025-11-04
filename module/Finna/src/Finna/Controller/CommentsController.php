<?php

/**
 * Comments Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use Finna\Db\Service\CommentsServiceInterface;

use function assert;

/**
 * Comments Controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CommentsController extends \VuFind\Controller\CommentsController
{
    use Feature\UserContentTrait;

    /**
     * Array of sort options for userListAction
     *
     * @var array
     */
    protected array $sortList = [
        'created desc' => 'hold_sort_create_desc',
        'created asc' => 'hold_sort_create_asc',
        'title' => 'sort_title',
    ];

    /**
     * Report inappropriate comment
     *
     * @return mixed
     */
    public function inappropriateAction()
    {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));

        if ($id && $this->formWasSubmitted()) {
            $reason = $this->params()->fromPost('reason');
            $message = $this->params()->fromPost('message', '');
            if (null !== $reason) {
                $this->markCommentInappropriate($id, $reason, $message);
                $this->flashMessenger()->addSuccessMessage('Reported inappropriate');
            } else {
                $this->flashMessenger()->addErrorMessage('Missing reason');
            }
        }

        return $this->createViewModel(['id' => $id]);
    }

    /**
     * Mark comment inappropriate.
     *
     * @param int    $id      Comment ID
     * @param string $reason  Reason
     * @param string $message Expand given reason
     *
     * @return void
     */
    protected function markCommentInappropriate($id, $reason, $message)
    {
        $user = $this->getUser();
        $sessionId = $this->serviceLocator->get(\Laminas\Session\SessionManager::class)->getId();
        $service = $this->getDbService(\VuFind\Db\Service\CommentsServiceInterface::class);
        assert($service instanceof CommentsServiceInterface);
        $service->markCommentInappropriate($user, (int)$id, $reason, $message, $sessionId);
    }
}
