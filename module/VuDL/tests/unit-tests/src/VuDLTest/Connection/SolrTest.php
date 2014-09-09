<?php
/**
 * VuDL Solr Manager Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuDLTest;

/**
 * VuDL Solr Manager Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SolrTest extends \VuFindTest\Unit\TestCase
{

    public function testAllWithMock()
    {
        $subject = new \VuDL\Connection\Solr(
            $this->getServiceManager()->get('VuFind\Config')->get('config'),
            new FakeBackend(
                array(
                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"modeltype_str_mv":["123456789012CLASS_ONE","123456789012CLASS_TWO"]}]}}',

                    false,
                    //'{"response":{"docs":[{"author":"1,2"}]}}',
                    //'{"response":{"docs":[{"author":"1,2"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"dc_title_str":"LABEL"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"id":"ID", "hierarchy_top_title":"TOP"}]}}',

                    false,
                    '{"response":{"numFound":1,"docs":[{"fgs.lastModifiedDate":["DATE"]}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"id":"ID1"},{"id":"ID2"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["id2"],"hierarchy_parent_title":["title2"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["id1"],"hierarchy_parent_title":["title1"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":[]}]}}',
                )
            )
        );

        $this->assertEquals(null, $subject->getClasses('id'));
        $this->assertEquals(array("CLASS_ONE", "CLASS_TWO"), $subject->getClasses('id'));

        $this->assertEquals(null, $subject->getDetails('id', false));
        // Test for exception later
        //$this->assertEquals(array("author"=>"1,2"), $subject->getDetails('id', false));
        //var_dump($subject->getDetails('id', true));
        //$this->assertEquals(array("author"=>array("1", "2")), $subject->getDetails('id', true));

        $this->assertEquals(null, $subject->getLabel('id'));
        $this->assertEquals("LABEL", $subject->getLabel('id'));

        $this->assertEquals(array(), $subject->getMemberList('root'));
        $this->assertEquals(array(array('id'=>'ID','title'=>'TOP')), $subject->getMemberList('root'));

        $this->assertEquals(null, $subject->getModDate('id'));
        $this->assertEquals("DATE", $subject->getModDate('id'));

        $this->assertEquals(null, $subject->getOrderedMembers('id'));
        $this->assertEquals(array("ID1", "ID2"), $subject->getOrderedMembers('id', array('fake_filter')));

        $this->assertEquals(null, $subject->getParentList('id1'));
        $this->assertEquals(array(array('id1'=>'title1','id2'=>'title2')), $subject->getParentList('id1'));
        $this->assertEquals(array(array('id1'=>'title1','id2'=>'title2')), $subject->getParentList('id1')); // Cache test
    }

    /**
     * Returns an array of classes for this object
     *
     * @param string $id record id
     *
     * @return array
     */
    protected function getClassesTest($id)
    {
    }

    /**
     * Get details from Solr
     *
     * @param string  $id     ID to look up
     * @param boolean $format Run result through formatDetails?
     *
     * @return array
     * @throws \Exception
     */
    protected function getDetailsTest($id, $format)
    {
    }

    /**
     * Get an item's label
     *
     * @param string $id Record's id
     *
     * @return string
     */
    protected function getLabelTest($id)
    {
    }

    /**
     * Tuple call to return and parse a list of members...
     *
     * @param string $root ...for this id
     *
     * @return array of members in order
     */
    protected function getMemberListTest($root)
    {
    }

    /**
     * Get the last modified date from Solr
     *
     * @param string $id ID to look up
     *
     * @return array
     * @throws \Exception
     */
    protected function getModDateTest($id)
    {
    }

    /**
     * Returns file contents of the structmap, our most common call
     *
     * @param string $id         record id
     * @param array  $extra_sort extra fields to sort on
     *
     * @return string $id
     */
    protected function getOrderedMembersTest($id, $extra_sort = array())
    {
    }

    /**
     * Tuple call to return and parse a list of parents...
     *
     * @param string $id ...for this id
     *
     * @return array of parents in order from top-down
     */
    protected function getParentListTest($id)
    {
    }

    /**
     * Get copyright URL and compare it to special cases from VuDL.ini
     *
     * @param array $id          record id
     * @param array $setLicenses ids are strings to match urls to,
     *  the values are abbreviations. Parsed in details.phtml later.
     *
     * @return array
     */
    protected function getCopyrightTest($id, $setLicenses)
    {
    }
}

class FakeBackend
{
    protected $returnList;

    public function __construct($returns)
    {
        $this->returnList = $returns;
    }

    public function getConnector()
    {
        return new FakeSolr($this->returnList);
    }
}

class FakeSolr
{
    protected $callNumber = 0;
    protected $returnList;

    public function __construct($returns)
    {
        $this->returnList = $returns;
    }

    public function getMap()
    {
        return new FakeMap();
    }

    public function search()
    {
        var_dump($this->returnList[$this->callNumber]);
        return $this->returnList[$this->callNumber++];
    }
}

class FakeMap
{
    public function __call($method, $args)
    {
        return new \ArrayObject();
    }
}