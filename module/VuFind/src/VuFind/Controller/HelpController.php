<?php

/**
 * Home action for Help module
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

/**
 * Home action for Help module
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HelpController extends AbstractBase
{
    /**
     * Uses the user language to determine which Help template to use
     * Uses the English template as a back-up
     *
     * @return mixed
     */
    public function homeAction()
    {
        $topic = $this->params()->fromRoute('topic');
        // The 'Home' check is for backward compatibility in case the legacy
        // Help/Home route is eventually removed from the configuration. Old
        // URLs were of the form /Help/Home?topic=x; new URLs are /Help/x.
        if (empty($topic) || $topic === 'Home') {
            $topic = $this->params()->fromQuery('topic');
        }

        $this->layout()->setTemplate('layout/help');
        return $this->createViewModel(
            ['topic' => $topic]
        );
    }
}
