<?php
/**
 * Comments Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;
use Zend\Session\Container as SessionContainer;

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
class CommentsController extends \Finna\Controller\AjaxController
{
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
            if (null !== $reason) {
                $this->markCommentInappropriate($id, $reason);
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
     * @param int    $id     Comment ID
     * @param string $reason Reason
     *
     * @return void
     */
    public function markCommentInappropriate($id, $reason)
    {
        $user = $this->getUser();

        $table = $this->getTable('Comments');
        $table->markInappropriate($user ? $user->id : null, $id, $reason);

        if (!$user) {
            $session = new SessionContainer('inappropriateComments');
            if (!isset($session->comments)) {
                $session->comments = [];
            }
            $session->comments[] = $id;
        }
    }
}
