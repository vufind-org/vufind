<?php

/**
 * Abstract base class for channels actions.
 *
 * PHP version 8
 *
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Channels;

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ChannelProvider\ChannelLoader;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for channels actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractChannelsAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param ChannelLoader $channelLoader Channel loader
     */
    #[Autowire()]
    public function __construct(
        protected ChannelLoader $channelLoader,
    ) {
        parent::__construct();
    }
}
