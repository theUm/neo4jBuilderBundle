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
use Nodeart\BuilderBundle\Entity\BookmarkNode;

class BookmarksQueries implements QueriesInterface {

    const MAIN_ALIAS = 'bm';

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
//		$cql = 'MATCH (%alias%:Comment) RETURN COUNT(%alias%) as count';
//		$cql = 'MATCH (n:Bookmark)--(u:User) where id(u) = 75  match(ref) where n.refId = id(ref) RETURN {bm:n, refNode:ref} COUNT(%alias%) as count';
        $cql = 'MATCH (%alias%:Bookmark)--(u:User) where id(u) = {uId}  MATCH(ref) WHERE %alias%.refId = id(ref) RETURN COUNT(%alias%) as count';
        $cql = str_replace( '%alias%', self::MAIN_ALIAS, $cql );

        $this->countQuery = $this->entityManager->createQuery( $cql );
        $this->countQuery->setParameter( 'uId', $this->getParam( 'user' ) );
    }

    public function getParam( $paramName ) {
        return $this->params[ $paramName ];
    }

    public function buildFilters(): string {
        $flatArrayFilters = [];
        foreach ( $this->getFilters() as $paramName => $props ) {
            $alias              = ( strpos( $paramName, '__' ) === false ) ? self::MAIN_ALIAS . '.' : str_replace( '__', '.', $paramName );
            $flatArrayFilters[] = $alias . $paramName . ' ' . $props['op'] . ' ' . $props['val'];
        }

        return empty( $flatArrayFilters ) ? '' : 'WHERE ' . join( ' AND ', $flatArrayFilters );
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

        $cql = 'MATCH (%alias%:Bookmark)--(u:User) where id(u) = {uId} 
                RETURN %alias% SKIP {skip} LIMIT {limit}';

        $cql = str_replace( '%alias%', self::MAIN_ALIAS, $cql );

        $this->query->setCQL( $cql );
        $this->query->setParameter( 'uId', $this->getParam( 'user' ) );
        $this->query->setParameter( 'limit', intval( $limit ) );
        $this->query->setParameter( 'skip', intval( $skip ) );
        $this->query->addEntityMapping( self::MAIN_ALIAS, BookmarkNode::class );
    }

    public function getQuery(): Query {
        return $this->query;
    }

    public function setParams( array $array ) {
        $this->params = $array;
    }

    public function setParam( $paramName, $paramValue ) {
        $this->params[ $paramName ] = $paramValue;

        return $this;
    }

    public function processOrder( array $getParams ) {
    }

    /**
     * @return array
     */
    public function getOrder(): array {
        return $this->order;
    }
}