<?php

/**
 * VuFind Action Helper - Digitization Requests Support Methods.
 *
 * PHP version 8
 *
 * Copyright (C) Mannheim University Library 2026.
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
 * @package  Action_Helper
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\ActionHelper;

use Laminas\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Date\DateException;
use VuFind\ILS\Connection;

use function in_array;
use function intval;

/**
 * Action helper to perform digitization request related actions.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Dennis Müller <dennis.mueller@uni-mannheim.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DigitizationRequestsHelper extends AbstractRequestBase
{
    /**
     * Update ILS details with cancellation-specific information, if appropriate.
     *
     * @param Connection $catalog      ILS connection object
     * @param array      $ilsDetails   Details from ILS driver's getMyDigitizationRequests() method
     * @param array      $cancelStatus Cancel settings from ILS driver's checkFunction() method
     * @param array      $patron       ILS patron
     *
     * @return array $ilsDetails with cancellation info added
     */
    public function addCancelDetails(
        Connection $catalog,
        array $ilsDetails,
        array $cancelStatus,
        array $patron = []
    ): array {
        // Generate Form Details for cancelling Digitization Requests if Cancelling is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == 'getCancelDigitizationRequestLink') {
                // Build OPAC URL
                $ilsDetails['cancel_link'] = $catalog->getCancelDigitizationRequestLink($ilsDetails, $patron);
            } elseif (isset($ilsDetails['cancel_details'])) {
                // The ILS driver provided cancel details up front. If the details are an empty string (flagging lack of
                // support), we should unset it to prevent confusion; otherwise, we'll leave it as-is.
                if ('' === $ilsDetails['cancel_details']) {
                    unset($ilsDetails['cancel_details']);
                } else {
                    $this->rememberValidId($ilsDetails['cancel_details']);
                }
            } else {
                // Default case: ILS supports cancel but we need to look up details:
                $cancelDetails = $catalog->getCancelDigitizationRequestDetails($ilsDetails, $patron);
                if ($cancelDetails !== '') {
                    $ilsDetails['cancel_details'] = $cancelDetails;
                    $this->rememberValidId($ilsDetails['cancel_details']);
                }
            }
        } else {
            // Cancelling disabled? Make sure no details get passed back:
            unset($ilsDetails['cancel_link']);
            unset($ilsDetails['cancel_details']);
        }

        return $ilsDetails;
    }

    /**
     * Process cancel request.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param Connection             $catalog  ILS connection object
     * @param array                  $patron   Current logged in patron
     *
     * @return array|ResponseInterface An associative array of cancellation results keyed by item ID (empty if no
     * cancellations performed), or a response if confirmation is required
     */
    public function cancelDigitizationRequests(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Connection $catalog,
        array $patron
    ): array|ResponseInterface {
        $postParams = $request->getParsedBody();

        // Pick IDs to cancel based on which button was pressed:
        $all = $postParams['cancelAll'] ?? null;
        $selected = $postParams['cancelSelected'] ?? null;
        if ($all) {
            $details = $postParams['cancelAllIDS'] ?? null;
        } elseif ($selected) {
            // Include cancelSelectedIDS for backwards-compatibility with legacy code:
            $details = $postParams['selectedIDS']
                ?? $postParams['cancelSelectedIDS']
                ?? null;
        } else {
            // No button pushed -- no action needed
            return [];
        }

        if (!empty($details)) {
            // Confirm?
            if (($postParams['confirm'] ?? null) === '0') {
                $targetUrl = $this->routeHelper->getUrlFromRoute('myresearch-digitizationrequests');
                if ($all !== null) {
                    $confirmTitle = 'digitization_request_cancel_all';
                    $confirmMessage = 'confirm_digitization_request_cancel_all_text';
                    $confirmExtras = [
                        'cancelAll' => 1,
                        'cancelAllIDS' => $details,
                    ];
                } else {
                    $confirmTitle = 'digitization_request_cancel_selected';
                    $confirmMessage = 'confirm_digitization_request_cancel_selected_text';
                    $confirmExtras = [
                        'cancelSelected' => 1,
                        'cancelSelectedIDS' => $details,
                    ];
                }
                return $this->forwardHelper->forwardToConfirm(
                    $request,
                    $response,
                    $confirmTitle,
                    $targetUrl,
                    $targetUrl,
                    $confirmMessage,
                    $confirmExtras
                );
            }

            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // legal list, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getValidIds())) {
                    $this->flashMessenger->addErrorMessage('error_inconsistent_parameters');
                    return [];
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelDigitizationRequests(compact('details', 'patron'));
            if ($cancelResults == false) {
                $this->flashMessenger->addErrorMessage('digitization_request_cancel_fail');
            } else {
                $failed = 0;
                foreach ($cancelResults['items'] ?? [] as $item) {
                    if (!$item['success']) {
                        ++$failed;
                    }
                }
                if ($failed) {
                    $this->flashMessenger->addErrorMessage(
                        [
                            'msg' => 'digitization_request_cancel_fail_items',
                            'tokens' => ['%%count%%' => $failed],
                        ]
                    );
                }
                if ($cancelResults['count'] > 0) {
                    $this->flashMessenger->addSuccessMessage(
                        [
                            'msg' => 'digitization_request_cancel_success_items',
                            'tokens' => ['%%count%%' => $cancelResults['count']],
                        ]
                    );
                }
                return $cancelResults;
            }
        } else {
            $this->flashMessenger->addErrorMessage('digitization_request_empty_selection');
        }
        return [];
    }

    /**
     * Validate digitization request input fields.
     *
     * @param array $gatheredDetails         Gathered form details
     * @param array $extraDigitizationFields Enabled extra fields
     *
     * @return array Array of error messages (empty if no errors)
     */
    public function validateDigitizationRequestInput(
        array $gatheredDetails,
        array $extraDigitizationFields
    ): array {
        $result = [
            'requiredByTS' => null,
            'errors' => [],
        ];

        if (in_array('digitizationType', $extraDigitizationFields)) {
            $digitizationType = $gatheredDetails['digitizationType'];
            if ($digitizationType == 'partial') {
                // If partial digitization, we need a partial digitization type (full or page range).
                if (empty($gatheredDetails['partialDigitizationType'])) {
                    $errors[] = 'digitization_request_partial_digitization_type_required';
                }
                // Make sure we have a valid page range.
                if ($gatheredDetails['partialDigitizationType'] == 'pageRange' && !$this->pageRangeIsValid($gatheredDetails['startPage'], $gatheredDetails['endPage'])) {
                    $errors[] = 'digitization_request_invalid_page_range';
                }
                // Make sure we have a chapter title
                if ($gatheredDetails['partialDigitizationType'] == 'full' && empty($gatheredDetails['chapterArticleTitle'])) {
                    $errors[] = 'digitization_request_chapter_article_title_required';
                }
            }
        }

        // Validate the required by date
        if (in_array('requiredByDate', $extraDigitizationFields)) {
            try {
                if ($requiredBy = $gatheredDetails['requiredBy']) {
                    $requiredByDateTime = \DateTime::createFromFormat(
                        'U',
                        $this->dateConverter->convertFromDisplayDate('U', $requiredBy)
                    );
                    $result['requiredByTS'] = $requiredByDateTime
                        ->setTime(23, 59, 59)
                        ->getTimestamp();
                } else {
                    $result['requiredByTS'] = 0;
                }
                if (
                    $result['requiredByTS'] && $result['requiredByTS'] < strtotime('today')
                ) {
                    $result['errors'][] = 'digitization_request_required_by_date_invalid';
                }
            } catch (DateException $e) {
                $result['errors'][] = 'digitization_request_required_by_date_invalid';
            }
        }

        $result['errors'] = $errors;
        return $result;
    }

    /**
     * Validate digitization request page range.
     *
     * @param mixed $startPage
     * @param mixed $endPage
     *
     * @return bool If the page range is valid
     */
    public function pageRangeIsValid($startPage, $endPage)
    {
        if (empty($startPage) || empty($endPage)) {
            return false;
        }
        if ((int)$startPage < 1) {
            return false;
        }
        // If pages are ints, floats or strings, check that start page is not larger than end page.
        // TODO: Can we account for roman numbers?
        if (is_numeric($startPage) && is_numeric($endPage)) {
            if (intval($startPage) > intval($endPage)) {
                return false;
            }
        }
        return true;
    }
}
