<?php
/**
 * VuDL Solr Test Class
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
 * VuDL Solr Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SolrTest extends \VuFindTest\Unit\TestCase
{
    public function testMissingDetails()
    {
        $subject = new \VuDL\Connection\Solr(
            (object) array('General'=>(object) array('page_length'=>8)),
            new FakeBackend(array('{"response":{"docs":[{"author":"1,2"}]}}'))
        );

        $this->assertEquals(8, $subject->getPageLength());

        try {
            $subject->getDetails('id', true);
        } catch(\Exception $e) {
            $this->assertEquals(
                'Missing [Details] in VuDL.ini',
                $e->getMessage()
            );
            return;
        }

        $this->fail('Exception not thrown for empty VuDL.ini details');
    }

    public function testAllWithMock()
    {
        $subject = new \VuDL\Connection\Solr(
            (object) array(
                'General'=>(object) array('root_id'=>'ROOT'),
                'Details'=>new FakeConfig(array('author,author2'=>'Author','series'=>'Series','bacon,eggs'=>'Yum','unused'=>':('))
            ),
            new FakeBackend(
                array(
                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"modeltype_str_mv":["123456789012CLASS_ONE","123456789012CLASS_TWO"]}]}}',

                    false,
                    '{"response":{"docs":[{"author":["A1","A2"],"series":"S1"}]}}',
                    '{"response":{"docs":[{"author":["A1","A2"],"series":"S1","bacon":"MORE"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"dc_title_str":"LABEL"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"id":"ID", "hierarchy_top_title":"TOP"}]}}',

                    false,
                    '{"response":{"numFound":1,"docs":[{"fgs.lastModifiedDate":["DATE"]}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"id":"ID1"},{"id":"ID2"}]}}',

                    '{"response":{"numFound":0}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["id2","id4"],"hierarchy_parent_title":["title2","title4"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["id3"],"hierarchy_parent_title":["title3"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["ROOT"],"hierarchy_parent_title":["ROOT"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"hierarchy_parent_id":["ROOT"],"hierarchy_parent_title":["ROOT"]}]}}',

                    '{"response":{"numFound":0,"docs":[0]}}',
                    '{"response":{"numFound":1,"docs":[{"license.mdRef":["vuABC"]}]}}',
                    '{"response":{"numFound":1,"docs":[{"license.mdRef":["vuABC"]}]}}'
                )
            )
        );

        $this->assertEquals(null, $subject->getClasses('id'));
        $this->assertEquals(array("CLASS_ONE", "CLASS_TWO"), $subject->getClasses('id'));

        $this->assertEquals(null, $subject->getDetails('id', false));
        $this->assertEquals(array("author"=>array("A1","A2"),"series"=>"S1"), $subject->getDetails('id', false));
        $this->assertEquals(array(
            "author"=>array("title"=>"Author", "value"=>array("A1","A2")),
            "bacon"=>array("title"=>"Yum", "value"=>array("MORE")),
            "series"=>array("title"=>"Series", "value"=>"S1"),
        ), $subject->getDetails('id', true));

        $this->assertEquals(null, $subject->getLabel('id'));
        $this->assertEquals("LABEL", $subject->getLabel('id'));

        $this->assertEquals(array(), $subject->getMemberList('root'));
        $this->assertEquals(array(array('id'=>'ID','title'=>'TOP')), $subject->getMemberList('root'));

        $this->assertEquals(null, $subject->getModDate('id'));
        $this->assertEquals("DATE", $subject->getModDate('id'));

        $this->assertEquals(null, $subject->getOrderedMembers('id'));
        $this->assertEquals(array("ID1", "ID2"), $subject->getOrderedMembers('id', array('fake_filter')));

        $this->assertEquals(null, $subject->getParentList('id1'));
        $this->assertEquals(array(array('id4'=>'title4'), array('id3'=>'title3','id2'=>'title2')), $subject->getParentList('id1'));
        // Cache test
        $this->assertEquals(array(array('id4'=>'title4'), array('id3'=>'title3','id2'=>'title2')), $subject->getParentList('id1'));

        $this->assertEquals(null, $subject->getCopyright('id', array()));
        $this->assertEquals(array("vuABC", "WTFPL"), $subject->getCopyright('id', array('A'=>'WTFPL')));
        $this->assertEquals(array("vuABC", false), $subject->getCopyright('id', array('X'=>'WTFPL')));

        $this->assertEquals(16, $subject->getPageLength());
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

class FakeConfig
{
    protected $value;

    public function __construct($v)
    {
        $this->value = $v;
    }

    public function toArray()
    {
        return $this->value;
    }
}