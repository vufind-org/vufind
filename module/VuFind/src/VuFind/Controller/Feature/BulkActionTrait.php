<?php

/**
 * VuFind Action Feature Trait - Bulk action helper methods
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

/**
 * VuFind Action Feature Trait - Bulk action helper methods
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait BulkActionTrait
{
    /**
     * Get the limit of a bulk action.
     *
     * @param string $action Name of the bulk action
     *
     * @return int
     */
    public function getBulkActionLimit($action)
    {
        return $this->configLoader->get('config')?->BulkActions?->limits?->$action
            ?? $this->configLoader->get('config')?->BulkActions?->limits?->default
            ?? 100;
    }

    /**
     * Get the limit of the export action for a specific format.
     *
     * @param string $format Name of the format
     *
     * @return int
     */
    public function getExportActionLimit($format)
    {
        return $this->configLoader->get('export')?->$format?->limit
            ?? $this->getBulkActionLimit('export');
    }

    /**
     * Support method: redirect to the page we were on when the bulk action was
     * initiated.
     *
     * @param string $flashNamespace Namespace for flash message (null for none)
     * @param string $flashMsg       Flash message to set (ignored if namespace null)
     *
     * @return mixed
     */
    public function redirectToSource($flashNamespace = null, $flashMsg = null, $redirectInLightbox = false)
    {
        // Set flash message if requested:
        if (null !== $flashNamespace && !empty($flashMsg)) {
            $this->flashMessenger()->addMessage($flashMsg, $flashNamespace);
        }

        // Do not redirect if in lightbox only if required
        if (
            !$this->params()->fromPost('redirectInLightbox', false)
            && !$redirectInLightbox
            && $this->inLightbox()
        ) {
            return false;
        }

        // If we entered the controller in the expected way (i.e. via the
        // myresearchbulk action), we should have a source set in the followup
        // memory. If that's missing for some reason, just forward to MyResearch.
        if (isset($this->session->url)) {
            $target = $this->session->url;
            unset($this->session->url);
        } else {
            $target = $this->url()->fromRoute('myresearch-home');
        }
        return $this->redirect()->toUrl($target);
    }
}
