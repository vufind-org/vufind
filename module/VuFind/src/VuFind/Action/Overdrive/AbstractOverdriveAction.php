<?php

/**
 * Abstract base class for Overdrive actions.
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

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\DigitalContent\OverdriveConnector;
use VuFind\Record\Loader as RecordLoader;
use VuFind\RecordDriver\SolrOverdrive;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for Overdrive actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractOverdriveAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param OverdriveConnector $overdriveConnector Overdrive connector
     * @param RecordLoader       $recordLoader       Record loader
     */
    #[Autowire]
    public function __construct(
        protected OverdriveConnector $overdriveConnector,
        protected RecordLoader $recordLoader,
    ) {
        parent::__construct();
    }

    /**
     * Load an Overdrive record.
     *
     * @param string $recordId Record ID
     *
     * @return SolrOverdrive
     */
    protected function loadOverdriveRecord(string $recordId): SolrOverdrive
    {
        return $this->recordLoader->load($recordId);
    }
}
