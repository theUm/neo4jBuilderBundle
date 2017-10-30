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
use Nodeart\BuilderBundle\Entity\UserNode;

class ManageUsersQueries implements QueriesInterface {

    const MAIN_ALIAS = 'user';

    const FIELD_USERNAME = 'username';
    const FIELD_EMAIL = 'email';
    const FIELD_CREATED_AT = 'createdAt';
    const FIELD_LIKES = 'likes';
    const FIELD_DISLIKES = 'dislikes';
    const FIELD_REPORTS = 'reports';
    const FIELD_REACTIONS = 'reactions';
    const FIELD_COMMENTS = 'comments';
    const FIELD_ENABLED = 'enabled';
    const FIELD_APPROVED = 'approved';

    const SYNTHETIC_FIELDS = [
        self::FIELD_LIKES,
        self::FIELD_DISLIKES,
        self::FIELD_REPORTS,
        self::FIELD_REACTIONS,
        self::FIELD_COMMENTS
    ];

    const POSSIBLE_FIELDS = [
        self::FIELD_USERNAME,
        self::FIELD_EMAIL,
        self::FIELD_CREATED_AT,
        self::FIELD_LIKES,
        self::FIELD_DISLIKES,
        self::FIELD_REPORTS,
        self::FIELD_REACTIONS,
        self::FIELD_COMMENTS,
        self::FIELD_ENABLED,
        self::FIELD_APPROVED,
    ];

    const DEFAULT_ORDER = [ self::MAIN_ALIAS . '.' . self::FIELD_CREATED_AT . ' ' . self::SORT_DESC ];

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

    public function __construct( $nm ) {
        $this->entityManager = $nm;
    }

    public function createCountQuery() {
        $cql = 'MATCH (%alias%:User) %filters% RETURN count(%alias%) as count';
        $cql = str_replace( '%alias%', self::MAIN_ALIAS, $cql );
        $cql = str_replace( '%filters%', $this->buildFilters(), $cql );

        $this->countQuery = $this->entityManager->createQuery( $cql );
    }

    public function buildFilters(): string {
        return '';
    }

    /**
     * @return array
     */
    public function getFilters(): array {
        return $this->filters;
    }

    public function getCountQuery(): Query {
        return $this->countQuery;
    }

    /**
     * Returns structure of first level comments + users ordered by createdAt DESC  and some of the related
     * second level comments ordered by createdAt ASC
     *
     * @param $limit
     * @param $skip
     */
    public function createQuery( $limit, $skip ) {

        $this->query = $this->entityManager->createQuery();

        $cql = 'MATCH (%alias%:User)
		 OPTIONAL MATCH (%alias%)-[rel:Reaction]-(c:Comment)
		 OPTIONAL MATCH (%alias%)-[rel2:commented]-(c2:Comment) 
		 RETURN %alias%,
		  count(distinct rel.liked) as likes,
		  count(distinct rel.disliked) as dislikes,
		  count(distinct rel.whined) as reports,
		  count(distinct rel) as reactions,
		  count(distinct rel2) as comments
		 %order% 
		 SKIP {skip} LIMIT {limit}';

        $cql = str_replace( '%alias%', self::MAIN_ALIAS, $cql );
        $cql = str_replace( '%order%', $this->buildOrders(), $cql );
        $cql = str_replace( '%filters%', $this->buildFilters(), $cql );

        $this->query->setCQL( $cql );
        $this->query->setParameter( 'limit', intval( $limit ) );
        $this->query->setParameter( 'skip', intval( $skip ) );
        $this->query->addEntityMapping( self::MAIN_ALIAS, UserNode::class );
    }

    public function buildOrders(): string {
        $flatArrayOrders = [];
        // we need to get string like "  label1.paramName ASC, label1.paramname2 DESC, label2.param3Name ASC "
        foreach ( $this->getOrder() as $param => $order ) {
            if ( in_array( $param, self::SYNTHETIC_FIELDS ) ) {
                $alias = '';
            } else {
                //if %paramName% already has label prefix - replace all "__"s to dots
                $alias = ( strpos( $param, '__' ) === false ) ? self::MAIN_ALIAS . '.' : str_replace( '__', '.', $param );
            }

            $flatArrayOrders[] = $alias . $param . ' ' . $order;
        }

        if ( empty( $flatArrayOrders ) ) {
            $flatArrayOrders = self::DEFAULT_ORDER;
        }
        $orderString = 'ORDER BY ' . join( ',', $flatArrayOrders );

        return $orderString;
    }

    /**
     * @inheritdoc
     */
    public function getOrder(): array {
        return $this->order;
    }

    /**
     * @inheritdoc
     */
    protected function setOrder( string $paramName, $sorting = self::SORT_DESC ): QueriesInterface {
        $this->order = [ 'param' => self::MAIN_ALIAS . '.' . $paramName, 'sorting' => $sorting ];

        return $this;
    }

    /**
     * Validates and leaves only proper order params from request params
     *
     * @param array $getParams
     */
    public function processOrder( array $getParams ) {
        $passedFields = array_intersect_key( $getParams, array_flip( self::POSSIBLE_FIELDS ) );
        $passedFields = array_filter( $passedFields, function ( $filterName ) {
            return in_array( $filterName, [ self::SORT_ASC, self::SORT_DESC ] );
        } );
        $this->order  = $passedFields;
    }

    /**
     * Validates and leaves only proper order params from request params
     *
     * @param string $filter
     */
    public function processFilter( $filter ) {
    }

    /**
     * @param string $filterName
     * @param string $value
     * @param string $operator
     *
     * @return QueriesInterface
     */
    public function setFilter( string $filterName, string $operator = '=', $value ): QueriesInterface {
        $this->filters[ $filterName ] = [ 'val' => $value, 'op' => $operator ];

        return $this;
    }

    public function getQuery(): Query {
        return $this->query;
    }

    public function getParam( $paramName ) {
        return $this->params[ $paramName ];
    }

    protected function setParams( array $array ) {
        $this->params = $array;
    }

    protected function setParam( $paramName, $paramValue ) {
        $this->params[ $paramName ] = $paramValue;

        return $this;
    }
}