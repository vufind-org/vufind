<?php
/**
 * "Search history label" view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * "Search history label" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HistoryLabel extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Label configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Translation helper
     *
     * @var TransEsc
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param array    $config     Label configuration
     * @param TransEsc $translator Translation helper
     */
    public function __construct(array $config, TransEsc $translator)
    {
        $this->config = $config;
        $this->translator = $translator;
    }

    /**
     * Return a label for the specified class (if configured).
     *
     * @param string $class The search class ID of the active search
     *
     * @return string
     */
    public function __invoke($class)
    {
        if (isset($this->config[$class])) {
            return $this->translator->__invoke($this->config[$class]) . ':';
        }
        return '';
    }
}