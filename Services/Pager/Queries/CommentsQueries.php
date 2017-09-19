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

	public function __construct( $nm ) {
		$this->entityManager = $nm;
	}

	public function createCountQuery() {

		$nodeId = $this->params[0];
		$type   = $this->params[1];

		$this->countQuery = $this->entityManager->createQuery(
			'MATCH (o:Object)<-[:is_comment_of]-(comment:Comment) 
			 WHERE id(o) = {oID} AND comment.refType = {refType} AND comment.level = 0 AND ( comment.reports < 5  OR comment.reports is null) 
             OPTIONAL MATCH (comment)--(childComment:Comment) WHERE childComment.level <> 0
             AND ( childComment.reports < 5  OR childComment.reports is null) 
			 RETURN count(DISTINCT comment) as count, count(DISTINCT childComment) as childsCount'
		);
		$this->countQuery->setParameter( 'oID', intval( $nodeId ) );
		$this->countQuery->setParameter( 'refType', $type );
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

		$nodeId = $this->params[0];
		$type   = $this->params[1];

		$this->query = $this->entityManager->createQuery();

		$cql = 'MATCH (o)<-[:is_comment_of]-(comment:Comment)-[]->(user:User) 
										WHERE id(o) = {oID}  AND comment.level=0 AND comment.refType = {refType} %olderThan%
										WITH comment, user ORDER BY comment.createdAt DESC %skip% limit {limit} 
									OPTIONAL MATCH (comment)<-[:is_child_of]-(child:Comment)-->(childUser:User)
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
		$this->query->setParameter( 'oID', intval( $nodeId ) );
		$this->query->setParameter( 'refType', $type );
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

}