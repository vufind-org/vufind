<?php

/**
 * Overdrive Content action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Overdrive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

use function is_array;

/**
 * Overdrive Content action.
 *
 * @category VuFind
 * @package  Action
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ContentAction extends AbstractOverdriveAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Display the Overdrive account page.
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
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $holds = [];
        $checkouts = [];
        $checkoutsUnavailable = false;
        $holdsUnavailable = false;

        // Check on this patrons's access to Overdrive:
        $odAccessResult = $this->overdriveConnector->getAccess();

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!($odAccessResult->status ?? false)) {
            $this->debug('result:' . print_r($odAccessResult, true));
            $flashMessagesHelper->addErrorMessage(
                $this->translate(
                    $odAccessResult->code ?? 'An error has occurred',
                    ['%%message%%' => $odAccessResult->msg ?? '']
                )
            );
            $checkoutsUnavailable = true;
            $holdsUnavailable = true;
        } else {
            // Get the current Overdrive checkouts for this user and add to our array of IDS:
            $checkoutResults = $this->overdriveConnector->getCheckouts(true);
            if (!($checkoutResults->status ?? false)) {
                $flashMessagesHelper->addErrorMessage($checkoutResults->code ?? 'An error has occurred');
                $checkoutsUnavailable = true;
            } else {
                foreach ($checkoutResults->data as $checkout) {
                    $mycheckout = compact('checkout');

                    if (($checkout->metadata->mediaType ?? '') == 'Magazine') {
                        $mycheckout['checkout']->isMagazine = true;
                        $this->debug("loading magazine metadata for {$checkout->reserveId}");
                        $idToLoad = strtolower($checkout->metadata->parentMagazineReferenceId);
                        $this->debug("loading magazine parent with id: $idToLoad instead");
                    } else {
                        $mycheckout['checkout']->isMagazine = false;
                        $idToLoad = strtolower($checkout->reserveId);
                    }

                    try {
                        $this->debug("loading checkout using: $idToLoad");
                        $mycheckout['record'] = $this->loadOverdriveRecord($idToLoad);
                        $checkouts[] = $mycheckout;
                    } catch (\VuFind\Exception\RecordMissing $e) {
                        $this->debug("missing record in index: $idToLoad");
                        // Checkout is missing from Solr
                        $flashMessagesHelper->addErrorMessage(
                            'One or more checkouts could not be displayed properly: ' . $e->getMessage()
                        );
                        // Get metadata from overdrive:
                        $meta = $this->overdriveConnector->getMetadata([strtolower($checkout->reserveId)]);
                        $mycheckout['metadata'] = $meta[strtolower($checkout->reserveId)];
                        $checkouts[] = $mycheckout;
                    }
                }
            }
            // Get the current Overdrive holds for this user and add to our array of IDS:
            $holdsResults = $this->overdriveConnector->getHolds(true);
            if (
                !($holdsResults->status ?? false)
                && ($checkoutResults->status ?? false) // avoid double errors
            ) {
                $flashMessagesHelper->addErrorMessage($holdsResults->code ?? 'An error has occurred');
                $holdsUnavailable = true;
            } else {
                foreach ($holdsResults->data as $hold) {
                    $myhold['hold'] = $hold;
                    try {
                        $myhold['record'] = $this->loadOverdriveRecord(strtolower($hold->reserveId));
                        $holds[] = $myhold;
                    } catch (\VuFind\Exception\RecordMissing $e) {
                        // Hold is missing from Solr
                        $flashMessagesHelper->addErrorMessage(
                            'One or more holds could not be displayed properly: ' . $e->getMessage()
                        );

                        // Get metadata from overdrive:
                        $meta = $this->overdriveConnector->getMetadata([$hold->reserveId]);
                        $myhold['metadata'] = $meta[$hold->reserveId];
                        $holds[] = $myhold;
                    }
                }
            }
        }

        $templateParams = compact('checkoutsUnavailable', 'holdsUnavailable', 'checkouts', 'holds');

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
