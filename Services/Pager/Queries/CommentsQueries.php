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

class CommentsQueries implements QueriesInterface {

	/**
	 * @var Query
	 */
	protected $countQuery;
	/**
	 * @var Query
	 */
	private $query;

	/**
	 * @var string
	 */
	protected $queryString;

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	private $params = [];
	private $filters = [];

	public function __construct( $nm ) {
		$this->entityManager = $nm;
	}

	public function createCountQuery() {

		$this->countQuery = $this->entityManager->createQuery(
			'MATCH (o:Object)<-[:is_comment_of]-(comment:Comment) 
			 WHERE id(o) = {oID} AND comment.refType = {refType} AND comment.level = 0 AND comment.status = {statusCode} 
             OPTIONAL MATCH (comment)--(childComment:Comment) WHERE childComment.level <> 0
             AND comment.status = {statusCode}
			 RETURN count(DISTINCT comment) as count, count(DISTINCT childComment) as childsCount'
		);
		$this->countQuery->setParameter( 'statusCode', CommentNode::STATUS_APPROVED );
		$this->countQuery->setParameter( 'oID', intval( $this->getParam( 'oId' ) ) );
		$this->countQuery->setParameter( 'refType', $this->getParam( 'type' ) );
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
	 * @param null $fromId
	 */
	public function createQuery( $limit, $skip, $fromId = null ) {
		$this->query = $this->entityManager->createQuery();

		$cql = 'MATCH (o)<-[:is_comment_of]-(comment:Comment)-[]->(user:User) 
										WHERE id(o) = {oID}  AND comment.level=0 AND comment.refType = {refType}
										AND comment.status = {statusCode}  %olderThan% 
										WITH comment, user ORDER BY comment.createdAt DESC %skip% limit {limit} 
									OPTIONAL MATCH (comment)<-[:is_child_of]-(child:Comment)-->(childUser:User)
									    WHERE child.status = {statusCode}
                                        WITH comment, user, child, childUser ORDER BY child.createdAt DESC limit 100
									RETURN comment, user, count(child) as count,  collect(
										CASE childUser WHEN NOT exists(childUser.name)
									    THEN null
									    ELSE {comment:child, user:childUser} END
									) AS childs
							        ORDER BY comment.createdAt DESC';

		// replace SKIP part if present
		if ( ! is_null( $skip ) ) {
			$cql = str_replace( '%skip%', 'SKIP {skip}', $cql );
			$this->query->setParameter( 'skip', $skip );
		} else {
			$cql = str_replace( '%skip%', '', $cql );
		}

		if ( ! is_null( $fromId ) ) {
			$cql = 'MATCH (refComment:Comment) where id(refComment) = {fromId} ' . $cql;
			$this->query->setParameter( 'olderThan', $skip );
			$cql = str_replace( '%olderThan%', ' AND comment.createdAt <= {olderThan}', $cql );
		} else {
			$cql = str_replace( '%olderThan%', '', $cql );
		}

		$this->query->setCQL( $cql );
		$this->query->setParameter( 'oID', intval( $this->getParam( 'oId' ) ) );
		$this->query->setParameter( 'statusCode', CommentNode::STATUS_APPROVED );
		$this->query->setParameter( 'refType', $this->getParam( 'type' ) );
		$this->query->setParameter( 'limit', intval( $limit ) );
		$this->query->addEntityMapping( 'comment', CommentNode::class );
		$this->query->addEntityMapping( 'user', UserNode::class );
		$this->query->addEntityMapping( 'childs', null, Query::HYDRATE_MAP_COLLECTION );
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

	public function getParam( $paramName ) {
		return $this->params[ $paramName ];
	}

	public function processOrder( array $getParams ) {
		// TODO: Implement processFilters() method.
	}

	public function setOrder( string $paramName, $sorting = self::SORT_DESC ): QueriesInterface {
		// TODO: Implement setOrder() method.
		return $this;
	}

	public function getOrder(): array {
		// TODO: Implement getOrder() method.
		return [];
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

	/**
	 * @return array
	 */
	public function getFilters(): array {
		return $this->filters;
	}
}