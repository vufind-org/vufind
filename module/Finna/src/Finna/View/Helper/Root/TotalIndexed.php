<?php
/**
 * Count of all indexed items view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Count of all indexed items view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class TotalIndexed extends \Zend\View\Helper\AbstractHelper
{

    /**
     * Total item count in index.
     * @return int count of indexed items or 0 if no information
     */
    public function getTotalIndexedCount()
    {
        if (isset($this->view->results)) {
            $query = $this->view->results->getParams()->getQuery();
            if (empty($query->getString())) {
                return $this->view->results->getResultTotal();
            } else {
                return 0;
            }
        }
        $layout = $this->view->layout();
        if ($layout !== null) {
            foreach ($layout->getChildren() as $child) {
                if ($child->getTemplate() == 'search/home') {
                    $results = $child->getVariables()['results'];
                    if (empty($results->getParams()->getQuery()->getString())) {
                        return $results->getResultTotal();
                    }
                }
            }
        }
        return 0;
    }

}
