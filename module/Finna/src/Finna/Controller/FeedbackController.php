<?php

/**
 * Feedback Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use VuFind\Log\LoggerAwareTrait;

/**
 * Feedback Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FeedbackController extends \VuFind\Controller\FeedbackController implements \Laminas\Log\LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.yaml.
     *
     * @return mixed
     */
    public function formAction()
    {
        // Copy any record_id from query params to post params so that it's available
        // for the form:
        $request = $this->getRequest();
        if (
            null === $request->getPost('record_id')
            && $recordId = $request->getQuery('record_id')
        ) {
            $request->getPost()->set('record_id', $recordId);
        }
        // Copy record data:
        if ($recordId = $request->getPost('record_id')) {
            [$source, $recId] = explode('|', $recordId, 2);
            $driver = $this->getRecordLoader()->load($recId, $source);
            if (null === $request->getPost('record_info')) {
                $recordPlugin = $this->getViewRenderer()->plugin('record');
                $request->getPost()
                    ->set('record_info', $recordPlugin($driver)->getEmail());
            }
            if (null === $request->getPost('record')) {
                $request->getPost()->set('record', $driver->getBreadcrumb());
            }
        }

        return parent::formAction();
    }
}
