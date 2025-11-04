<?php

/**
 * Record preview support trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Controller\Feature;

use Finna\Controller\Plugin\Preview;
use Laminas\Session\Exception\RuntimeException as SessionRuntimeException;
use VuFindSearch\ParamBag;

use function is_object;

/**
 * Record preview support trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaRecordPreviewSupportTrait
{
    /**
     * Any preview record validation result
     *
     * @var ?int
     */
    protected ?int $validationResult = null;

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @param ?ParamBag $params Search backend parameters
     * @param bool      $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(?ParamBag $params = null, bool $force = false)
    {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        // 0 = preview record
        if ($id != '0') {
            return parent::loadRecord($params, $force);
        }

        if (!$force && is_object($this->driver)) {
            return $this->driver;
        }

        $result = $this->plugin('preview')->loadAndValidatePreviewRecord();
        $this->driver = $result['driver'];
        $this->validationResult = $result['validation_result'];
        // Add flash messages about any load issues:
        foreach ($result['errors'] as $error) {
            $this->flashMessenger()->addErrorMessage($error);
        }
        return $this->driver;
    }

    /**
     * Add any record validation result as a flash message.
     *
     * @return void
     */
    protected function addValidationResultMessage()
    {
        // Add flash messages about any validation issues:
        if ($this->validationResult) {
            $msg = Preview::VALIDATION_ERRORS === $this->validationResult
                ? 'Validation::metadata_errors_html'
                : 'Validation::metadata_issues_html';
            try {
                $this->flashMessenger()->addErrorMessage(
                    [
                        'msg' => $msg,
                        'tokens' => ['%%url%%' => $this->url()->fromRoute('record-validationreport', ['id' => 0])],
                        'html' => true,
                    ]
                );
            } catch (SessionRuntimeException $e) {
                // This will fail in tabs etc. where session is immutable
            }
        }
    }
}
