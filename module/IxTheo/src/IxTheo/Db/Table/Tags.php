<?php

namespace IxTheo\Db\Table;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\Tags as VuFindTags;
use VuFind\Db\Table\Resource as Resource;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Select;

class Tags extends VuFindTags
{

    protected $filtered_resource_tags = 'resource_tags';


    protected function determineFilteredResourceTable() {
      $instance_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
      if ($instance_type === 'ixtheo')
          $this->filtered_resource_tags = 'resource_tags';
      else
          $this->filtered_resource_tags = 'resource_tags' . '_' . $instance_type;
      return $this->filtered_resource_tags;
    }


    /**
     * Get the tags that match a string
     *
     * @param string $text  Tag to look up.
     * @param string $sort  Sort/search parameter
     * @param int    $limit Maximum number of tags
     *
     * @return array Array of \VuFind\Db\Row\Tags objects
     */
    public function getTagList($sort, $limit = 100, $extra_where = null)
    {

        $callback = function ($select) use ($sort, $limit, $extra_where) {
            $filtered_resource_tags = $this->determineFilteredResourceTable();
            $select->columns(
                [
                    'id',
                    'tag' => $this->caseSensitive
                        ? 'tag' : new Expression('lower(tag)'),
                    'cnt' => new Expression(
                        'COUNT(DISTINCT(?))', [$filtered_resource_tags . '.resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'posted' => new Expression(
                        'MAX(?)', [$filtered_resource_tags . '.posted'],
                        [Expression::TYPE_IDENTIFIER]
                    )
                ]
            );
            $select->join(
                $filtered_resource_tags, 'tags.id = ' . $filtered_resource_tags . '.tag_id', []
            );
            if (is_callable($extra_where)) {
                $extra_where($select);
            }
            $select->group(['tags.id', 'tags.tag']);
            switch ($sort) {
            case 'alphabetical':
                $select->order([new Expression('lower(tags.tag)'), 'cnt DESC']);
                break;
            case 'popularity':
               $select->order(['cnt DESC', new Expression('lower(tags.tag)')]);
                break;
            case 'recent':
                $select->order(
                    ['posted DESC', 'cnt DESC', new Expression('lower(tags.tag)')]
                );
                break;
            }
            // Limit the size of our results
            if ($limit > 0) {
                $select->limit($limit);
            }
        };

        $tagList = [];
        foreach ($this->select($callback) as $t) {
            $tagList[] = [
                'tag' => $t->tag,
                'cnt' => $t->cnt
            ];
        }
        return $tagList;
    }


    public function resourceSearch($q, $source = null, $sort = null,
        $offset = 0, $limit = null, $fuzzy = true
    ) {
        $this->determineFilteredResourceTable();
        $cb = function ($select) use ($q, $source, $sort, $offset, $limit, $fuzzy) {
            $select->columns(
                [
                    new Expression(
                        'DISTINCT(?)', ['resource.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->join(
                ['rt' => $this->filtered_resource_tags],
                'tags.id = rt.tag_id',
                []
            );
            $select->join(
                ['resource' => 'resource'],
                'rt.resource_id = resource.id',
                '*'
            );
            if ($fuzzy) {
                $select->where->literal('lower(tags.tag) like lower(?)', [$q]);
            } else if (!$this->caseSensitive) {
                $select->where->literal('lower(tags.tag) = lower(?)', [$q]);
            } else {
                $select->where->equalTo('tags.tag', $q);
            }

            if (!empty($source)) {
                $select->where->equalTo('source', $source);
            }

            if (!empty($sort)) {
               Resource::applySort($select, $sort);
            }

            if ($offset > 0) {
                $select->offset($offset);
            }
            if (null !== $limit) {
                $select->limit($limit);
            }
        };
        return $this->select($cb);
    }
}
