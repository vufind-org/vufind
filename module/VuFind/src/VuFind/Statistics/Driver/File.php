<?php
/**
 * File Statistics Driver
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
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Statistics\Driver;

/**
 * Writer to put statistics into an XML File
 *
 * @category VuFind2
 * @package  Statistics
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class File extends AbstractBase
{
    /**
     * Folder where stats will be written.
     *
     * @var string
     */
    protected $folder;

    /**
     * Constructor
     *
     * @param string $folder Folder where stats will be written.
     */
    public function __construct($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Get the name of the folder for storing statistics.
     *
     * @return string
     */
    protected function getFolder()
    {
        return $this->folder;
    }

    /**
     * Write a message to the log.
     *
     * @param array $data     Data specific to what we're saving
     * @param array $userData Browser, IP, urls, etc
     *
     * @throws \Exception
     * @return void
     */
    public function write($data, $userData)
    {
        $xml = $this->getSaveXML(array_merge($data, $userData), 1);
        $filename = rtrim($this->getFolder(), '/');
        if (!file_exists($filename)) {
            mkdir($filename);
        }
        $file = strtolower($this->getSource());
        $index = (strrpos($file, '.')) ? -1 : strlen($file);
        $filename .= '/' . substr($file, 0, $index) . '.xml';
        if (file_exists($filename)) {
            $file = file_get_contents($filename);
            // remove <xml .. >
            $xml .= "\n" . substr($file, 39, strlen($file));
        } else {
            $xml .= "\n</xml>";
        }
        $file = fopen($filename, 'w');
        if (!$file) {
            throw new \Exception('Cannot write to log file.');
        }
        fwrite($file, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $xml);
        fclose($file);
    }

    /**
     * Convert data array to XML
     *
     * @param array   $data Associative array of data
     * @param integer $tab  How far the string should be indented with tabs
     *
     * @return string
     */
    protected function getSaveXML($data, $tab = 0)
    {
        $xml = str_repeat("\t", $tab) . "<doc>\n";
        $tab++;
        foreach ($data as $tag => $value) {
            $xml .= str_repeat("\t", $tab);
            $insert = (strtolower(gettype($value)) == 'boolean')
                ? ($value)
                    ? 'true'
                    : 'false'
                : $value;
            $xml .= '<field name="' . $tag . '">' . $insert . "</field>\n";
        }
        $tab--;
        return $xml . str_repeat("\t", $tab) . "</doc>";
    }
}
