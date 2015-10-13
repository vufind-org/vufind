<?php
/**
 * VuDL connection base class (defines some methods to talk to VuDL sources)
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
namespace VuDL\Connection;
use VuFindHttp\HttpServiceInterface,
    VuFindSearch\ParamBag;

/**
 * VuDL connection base class
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 * @link     http://vufind.org/wiki/
 */
class AbstractBase implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * VuDL config
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Parent List data cache
     *
     * @var array
     */
    protected $parentLists = [];

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Get root id from config
     *
     * @return string
     */
    protected function getRootId()
    {
        return isset($this->config->General->root_id)
            ? $this->config->General->root_id
            : null;
    }

    /**
     * Get VuDL detail fields.
     *
     * @return array
     */
    protected function getDetailsList()
    {
        return isset($this->config->Details)
            ? $this->config->Details->toArray()
            : [];
    }

    /**
     * Get Fedora Page Length.
     *
     * @return string
     */
    public function getPageLength()
    {
        return isset($this->config->General->page_length)
            ? $this->config->General->page_length
            : 16;
    }

    /**
     * Format details properly into the correct keys
     *
     * @param array $record Record object
     *
     * @return string
     */
    protected function formatDetails($record)
    {
        // Format details
        // Get config for which details we want
        $detailsList = $this->getDetailsList();
        if (empty($detailsList)) {
            throw new \Exception('Missing [Details] in VuDL.ini');
        }
        $details = [];
        foreach ($detailsList as $key => $title) {
            $keys = explode(',', $key);
            $field = false;
            for ($i = 0;$i < count($keys);$i++) {
                if (isset($record[$keys[$i]])) {
                    $field = $keys[$i];
                    break;
                }
            }
            if (false === $field) {
                continue;
            }
            if (count($keys) == 1) {
                if (isset($record[$keys[0]])) {
                    $details[$field] = [
                        'title' => $title,
                        'value' => $record[$keys[0]]
                    ];
                }
            } else {
                $value = [];
                foreach ($keys as $k) {
                    if (isset($record[$k])) {
                        if (is_array($record[$k])) {
                            $value = array_merge($value, $record[$k]);
                        } else {
                            $value[] = $record[$k];
                        }
                    }
                }
                $details[$field] = [
                    'title' => $title,
                    'value' => $value
                ];
            }
        }
        return $details;
    }

    /**
     * A method to search from the root id down to the current record
     *   creating multiple breadcrumb paths along the way
     *
     * @param array  $tree Array of parents by id with title and array of children
     * @param string $id   Target id to stop at
     *
     * @return array Array of arrays with parents in order
     */
    protected function traceParents($tree, $id)
    {
        // BFS from top (root id) to target $id
        $queue = [
            [
                'id' => $this->getRootId(),
                'path' => []
            ]
        ];
        $ret = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $record = $tree[$current['id']];
            $path = $current['path'];
            if ($current['id'] != $this->getRootId()) {
                $path[$current['id']] = $record['title'];
            }
            foreach ($record['children'] as $cid) {
                // At target
                if ($cid == $id) {
                    array_push($ret, $path);
                } else { // Add to queue for more
                    array_push(
                        $queue,
                        [
                            'id' => $cid,
                            'path' => $path
                        ]
                    );
                }
            }
        }
        return $ret;
    }
}