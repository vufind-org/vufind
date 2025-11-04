<?php

/**
 * Collection Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017-2018.
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
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use Finna\Controller\Feature\FinnaRecordPreviewSupportTrait;

/**
 * Collection Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Anna Niku <anna.niku@gofore.com>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CollectionController extends \VuFind\Controller\CollectionController
{
    use \Finna\Statistics\ReporterTrait;
    use FinnaRecordPreviewSupportTrait;

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Call for login modal
        if (
            $this->inLightbox()
            && $this->params()->fromQuery('catalogLogin', 'false') == 'true'
        ) {
            return $this->catalogLogin();
        }

        return parent::showTab($tab, $ajax);
    }

    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        $result = parent::homeAction();
        $this->triggerStatsRecordView($result->driver ?? null);
        $this->addValidationResultMessage();
        return $result;
    }
}
