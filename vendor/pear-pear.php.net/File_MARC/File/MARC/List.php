<?php

/* vim: set expandtab shiftwidth=4 tabstop=4 softtabstop=4 foldmethod=marker: */

/**
 * Parser for MARC records
 *
 * This package is based on the PHP MARC package, originally called "php-marc",
 * that is part of the Emilda Project (http://www.emilda.org). Christoffer
 * Landtman generously agreed to make the "php-marc" code available under the
 * GNU LGPL so it could be used as the basis of this PEAR package.
 * 
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 2.1 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category  File_Formats
 * @package   File_MARC
 * @author    Christoffer Landtman <landtman@realnode.com>
 * @author    Dan Scott <dscott@laurentian.ca>
 * @copyright 2003-2008 Oy Realnode Ab, Dan Scott
 * @license   http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/File_MARC
 */

// {{{ class File_MARC_List extends SplDoublyLinkedList
/**
 * The File_MARC_List class extends the SplDoublyLinkedList class
 * to override the key() method in a meaningful way for foreach() iterators.
 *
 * For the list of {@link File_MARC_Field} objects in a {@link File_MARC_Record}
 * object, the key() method returns the tag name of the field.
 * 
 * For the list of {@link File_MARC_Subfield} objects in a {@link
 * File_MARC_Data_Field} object, the key() method returns the code of
 * the subfield.
 *
 * <code>
 * // Iterate through the fields in a record with key=>value iteration
 * foreach ($record->getFields() as $tag=>$value) {
 *     print "$tag: ";
 *     if ($value instanceof File_MARC_Control_Field) {
 *         print $value->getData();
 *     }
 *     else {
 *         // Iterate through the subfields in this data field
 *         foreach ($value->getSubfields() as $code=>$subdata) {
 *             print "_$code";
 *         }
 *     }
 *     print "\n";
 * }
 * </code>
 *
 * @category File_Formats
 * @package  File_MARC
 * @author   Dan Scott <dscott@laurentian.ca>
 * @license  http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @link     http://pear.php.net/package/File_MARC
 */
class File_MARC_List extends SplDoublyLinkedList
{

    // {{{ properties
    /**
     * Position of the subfield
     * @var int
     */
    protected $position;

    // }}}

    // {{{ key()
    /**
     * Returns the tag for a {@link File_MARC_Field} object, or the code
     * for a {@link File_MARC_Subfield} object.
     *
     * This method enables you to use a foreach iterator to retrieve
     * the tag or code as the key for the iterator.
     *
     * @return string returns the tag or code
     */
    function key()
    {
        if ($this->current() instanceof File_MARC_Field) {
            return $this->current()->getTag();
        } elseif ($this->current() instanceof File_MARC_Subfield) {
            return $this->current()->getCode();
        }
        return false;
    }
    // }}}

    // {{{ function insertNode()
    /**
     * Inserts a node into the linked list, based on a reference node that
     * already exists in the list.
     *
     * @param mixed $new_node      New node to add to the list
     * @param mixed $existing_node Reference position node
     * @param bool  $before        Insert new node before or after the existing node
     *
     * @return bool Success or failure
     **/
    public function insertNode($new_node, $existing_node, $before = false)
    {
        $pos = 0;
        $exist_pos = $existing_node->getPosition();
        $temp_list = unserialize(serialize($this));
        $this->rewind();
        $temp_list->rewind();

        // Now add the node according to the requested mode
        switch ($before) {

        case true:
            $new_node->setPosition($exist_pos);

            if ($exist_pos == 0) {
                $this->unshift($new_node);
                while ($n = $temp_list->next()) {
                    $pos++;
                    $this->next()->setPosition($pos);
                }
            } else {
                $prev_node = $temp_list->offsetGet($existing_node->getPosition());
                $num_nodes = $this->count();
                $this->rewind();
                // Copy up to the existing position, add in node, copy rest
                try {
                    while ($n = $temp_list->shift()) {
                        $this->next();
                        $pos++;
                        if ($pos < $exist_pos) {
                            // no-op
                        } elseif ($pos == $exist_pos) {
                            $this->offsetSet($pos, $new_node);
                        } elseif ($pos == $num_nodes) {
                            $n->setPosition($pos);
                            $this->push($n);
                        } elseif ($pos > $exist_pos) {
                            $n->setPosition($pos);
                            $this->offsetSet($pos, $n);
                        }
                    }
                }
                catch (Exception $e) {
                    // no-op - shift() throws an exception, sigh
                }
            }
            break;

        // after
        case false:
            $prev_node = $temp_list->offsetGet($existing_node->getPosition());
            $num_nodes = $this->count();
            $this->rewind();
            // Copy up to the existing position inclusively, add node, copy rest
            try {
                while ($n = $temp_list->shift()) {
                    $this->next();
                    $pos++;
                    if ($pos <= $exist_pos) {
                        // no-op
                    } elseif ($pos == $exist_pos + 1) {
                        $this->offsetSet($pos, $new_node);
                    } elseif ($pos == $num_nodes) {
                        $n->setPosition($pos);
                        $this->push($n);
                    } elseif ($pos > $exist_pos + 1) {
                        $n->setPosition($pos);
                        $this->offsetSet($pos, $n);
                    }
                }
            }
            catch (Exception $e) {
                // no-op - shift() throws an exception, sigh
            }
            break;
        }

        return true;
    }
    // }}}

    // {{{ function appendNode()
    /**
     * Adds a node onto the linked list.
     *
     * @param mixed $new_node New node to add to the list
     *
     * @return void
     **/
    public function appendNode($new_node)
    {
        $pos = $this->count();
        $new_node->setPosition($pos);
        $this->push($new_node);
    }
    // }}}

    // {{{ function prependNode()
    /**
     * Adds a node to the start of the linked list.
     *
     * @param mixed $new_node New node to add to the list
     *
     * @return void
     **/
    public function prependNode($new_node)
    {
        $this->insertNode($new_node, $this->bottom(), true);
    }
    // }}}

    // {{{ function deleteNode()
    /**
     * Deletes a node from the linked list.
     *
     * @param mixed $node Node to delete from the list
     *
     * @return void
     **/
    public function deleteNode($node)
    {
        $target_pos = $node->getPosition();
        $this->rewind();
        $pos = 0;

        // Omit target node and adjust pos of remainder
        try {
            while ($n = $this->current()) {
                if ($pos == $target_pos) {
                    $this->offsetUnset($pos);
                } elseif ($pos > $target_pos) {
                    $n->setPosition($pos);
                }
                $pos++;
                $this->next();
            }
        }
        catch (Exception $e) {
            // no-op - shift() throws an exception, sigh
        }

    }
    // }}}

    // {{{ setPosition()
    /**
     * Sets position of the subfield
     *
     * @param string $pos new position of the subfield
     *
     * @return void
     */
    function setPosition($pos)
    {
        $this->position = $pos;
    }
    // }}}

    // {{{ getPosition()
    /**
     * Return position of the subfield
     *
     * @return int data
     */
    function getPosition()
    {
        return $this->position;
    }
    // }}}

}
// }}}

