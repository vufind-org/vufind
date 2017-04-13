<?php
/**
 * Minifier wrapper trait
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace FinnaTheme\Minify;
use Finna\Db\Table\FinnaCache;

/**
 * Minifier wrapper trait
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait MinifyTrait
{
    /**
     * FinnaCache table
     *
     * @var FinnaCache
     */
    protected $finnaCache;

    /**
     * Constructor
     *
     * @param FinnaCache $finnaCache FinnaCache table
     */
    public function __construct(FinnaCache $finnaCache)
    {
        parent::__construct();
        $this->finnaCache = $finnaCache;
    }

    /**
     * Minify the data & (optionally) saves it to a file.
     *
     * @param string $path Optional oath to write the data to
     *
     * @return string The minified data
     */
    public function minify($path = null)
    {
        // Check for minified version in the database
        if (null !== $path) {
            $filename = basename($path);
            $row = $this->finnaCache->getByResourceId($filename);
            if (false !== $row) {
                if (false === file_put_contents($path, $row->data)) {
                    throw new \Exception("Could not write to file $path");
                }
                if (false === touch($path, $row->mtime)) {
                    throw new \Exception("Could not touch timestamp of $path");
                }
                return $row->data;
            }
        }

        $content = parent::minify($path);

        // Save to database
        if (null !== $path) {
            $mtime = filemtime($path);
            if (false === $mtime) {
                throw new \Exception("Could not get mtime of $path");
            }
            $row = $this->finnaCache->createRow();
            $row->resource_id = $filename;
            $row->mtime = $mtime;
            $row->data = $content;
            $row->save();
        }

        return $content;
    }
}
