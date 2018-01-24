<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 25.05.2017
 * Time: 12:51
 */

namespace Nodeart\BuilderBundle\Services\Pager\Queries;

use GraphAware\Neo4j\OGM\EntityManager;
use GraphAware\Neo4j\OGM\Query;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class ManageCommentsQueries implements QueriesInterface
{

    const MAIN_ALIAS = 'comment';

    const FIELD_USERNAME = 'user__username';
    const FIELD_COMMENT = 'comment';
    const FIELD_CREATED_AT = 'createdAt';
    const FIELD_LIKES = 'likes';
    const FIELD_DISLIKES = 'dislikes';
    const FIELD_REPORTS = 'reports';
    const FIELD_REF_TYPE = 'refType';
    const FIELD_STATUS = 'status';

    const POSSIBLE_FIELDS = [
        self::FIELD_USERNAME,
        self::FIELD_COMMENT,
        self::FIELD_CREATED_AT,
        self::FIELD_LIKES,
        self::FIELD_DISLIKES,
        self::FIELD_REPORTS,
        self::FIELD_REF_TYPE,
        self::FIELD_STATUS
    ];

    const DEFAULT_ORDER = [self::MAIN_ALIAS . '.' . self::FIELD_CREATED_AT . ' ' . self::SORT_DESC];

    /**
     * @var Query
     */
    protected $countQuery;
    /**
     * @var string
     */
    protected $queryString;
    /**
     * @var Query
     */
    private $query;
    /**
     * @var EntityManager
     */
    private $entityManager;

    private $params = [];
    private $order = [];
    private $filters = [];

    public function __construct($nm)
    {
        $this->entityManager = $nm;
    }

    public function createCountQuery()
    {
        $cql = 'MATCH (o:Object)<-[:is_comment_of]-(%alias%:Comment) %filters% RETURN count(%alias%) as count';
        $cql = str_replace('%alias%', self::MAIN_ALIAS, $cql);
        $cql = str_replace('%filters%', $this->buildFilters(), $cql);

        $this->countQuery = $this->entityManager->createQuery($cql);
    }

    public function buildFilters(): string
    {
        $flatArrayFilters = [];
        foreach ($this->getFilters() as $paramName => $props) {
            $alias = (strpos($paramName, '__') === false) ? self::MAIN_ALIAS . '.' : str_replace('__', '.', $paramName);
            $flatArrayFilters[] = $alias . $paramName . ' ' . $props['op'] . ' ' . $props['val'];
        }

        return empty($flatArrayFilters) ? '' : 'WHERE ' . join(' AND ', $flatArrayFilters);
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getCountQuery(): Query
    {
        return $this->countQuery;
    }

    /**
     * Returns structure of first level comments + users ordered by createdAt DESC  and some of the related
     * second level comments ordered by createdAt ASC
     *
     * @param $limit
     * @param $skip
     */
    public function createQuery($limit, $skip)
    {

        $this->query = $this->entityManager->createQuery();

        $cql = 'MATCH (ref)<-[rel_ref:is_comment_of]-(%alias%:Comment)-[:commented]->(user:User)
		 %filters%
		 RETURN %alias%, user, ref %order% SKIP {skip} LIMIT {limit}';

        $cql = str_replace('%alias%', self::MAIN_ALIAS, $cql);
        $cql = str_replace('%order%', $this->buildOrders(), $cql);
        $cql = str_replace('%filters%', $this->buildFilters(), $cql);

        $this->query->setCQL($cql);
        $this->query->setParameter('limit', intval($limit));
        $this->query->setParameter('skip', intval($skip));
        $this->query->addEntityMapping(self::MAIN_ALIAS, CommentNode::class);
        $this->query->addEntityMapping('user', UserNode::class);
    }

    public function buildOrders(): string
    {
        $flatArrayOrders = [];
        // we need to get string like "  label1.paramName ASC, label1.paramname2 DESC, label2.param3Name ASC "
        foreach ($this->getOrder() as $param => $order) {
            //if %paramName% already has label prefix - replace all "__"s to dots
            $alias = (strpos($param, '__') === false) ? self::MAIN_ALIAS . '.' : str_replace('__', '.', $param);
            $flatArrayOrders[] = $alias . $param . ' ' . $order;
        }
        if (empty($flatArrayOrders)) {
            $flatArrayOrders = self::DEFAULT_ORDER;
        }
        $orderString = 'ORDER BY ' . join(',', $flatArrayOrders);

        return $orderString;
    }

    /**
     * @inheritdoc
     */
    public function getOrder(): array
    {
        return $this->order;
    }

    /**
     * @inheritdoc
     */
    protected function setOrder(string $paramName, $sorting = self::SORT_DESC): QueriesInterface
    {
        $this->order = ['param' => self::MAIN_ALIAS . '.' . $paramName, 'sorting' => $sorting];

        return $this;
    }

    /**
     * Validates and leaves only proper order params from request params
     *
     * @param array $getParams
     */
    public function processOrder(array $getParams)
    {
        $passedFields = array_intersect_key($getParams, array_flip(self::POSSIBLE_FIELDS));
        $passedFields = array_filter($passedFields, function ($filterName) {
            return in_array($filterName, [self::SORT_ASC, self::SORT_DESC]);
        });
        $this->order = $passedFields;
    }

    /**
     * Validates and leaves only proper order params from request params
     *
     * @param string $filter
     */
    public function processFilter($filter)
    {
        if (is_numeric($filter) && in_array($filter, array_keys(CommentNode::STATUSES), true)) {
            $this->setFilter('status', '=', $filter);
        };
    }

    /**
     * @param string $filterName
     * @param string $value
     * @param string $operator
     *
     * @return QueriesInterface
     */
    public function setFilter(string $filterName, string $operator = '=', $value): QueriesInterface
    {
        $this->filters[$filterName] = ['val' => $value, 'op' => $operator];

        return $this;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getParam($paramName)
    {
        return $this->params[$paramName];
    }

    public function setParams(array $array)
    {
        $this->params = $array;
    }

    public function setParam($paramName, $paramValue)
    {
        $this->params[$paramName] = $paramValue;

        return $this;
    }
}