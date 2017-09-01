<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\OGM\Query;
use GraphAware\Neo4j\OGM\Repository\BaseRepository;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class CommentNodeRepository extends BaseRepository {


	/**
	 * Returns structure of first level comments + users ordered by createdAt DESC  and some of the related
	 * second level comments ordered by createdAt ASC
	 *
	 * @param $nodeId
	 * @param $type
	 * @param int $limit
	 *
	 * @return array|mixed
	 */
	public function findCommentsByRefIdAndType( $nodeId, $type, $limit = 10 ) {
		//$query = $this->entityManager->createQuery( 'MATCH (comment:Comment)-->(ref) WHERE id(ref) = {refId} and comment.refType = {refType} AND comment.createdAt > 0 RETURN comment ORDER BY comment.order LIMIT {limit}' );

		$query = $this->entityManager->createQuery( 'MATCH (o:Object)<-[:is_comment_of]-(comment:Comment)-[]->(user:User) 
										WHERE id(o) = {oID}  AND comment.level=0 AND comment.refType = {refType}
										WITH comment, user ORDER BY comment.createdAt DESC limit {limit}
									OPTIONAL MATCH (comment)<-[:is_child_of]-(child:Comment)-->(childUser:User)
                                        WITH comment, user, child, childUser ORDER BY child.createdAt DESC limit 100
									RETURN comment, user, 
										CASE childUser 
											WHEN NOT exists(childUser.name) 
											THEN [] 
											ELSE collect({comment:child, user:childUser}) 
										END AS childs
							        ORDER BY comment.createdAt DESC' );

		$query->setParameter( 'oID', intval($nodeId) );
		$query->setParameter( 'refType', $type );
		$query->setParameter( 'limit', intval($limit) );
		$query->addEntityMapping( 'comment', CommentNode::class );
		$query->addEntityMapping( 'user', UserNode::class );
		$query->addEntityMapping( 'childs', null, Query::HYDRATE_MAP_COLLECTION );

		return $query->execute();
	}

	/**
	 * @param $parentId
	 * @param $type
	 *
	 * @return CommentNode|null
	 */
	public function findLastCommentChild( $parentId, $type ): ?CommentNode {
		$query = $this->entityManager->createQuery(
			'MATCH (comment:Comment)<-[:is_child_of]-(child:Comment) WHERE id(comment) = {comId} AND comment.refType = {refType} AND child.level = 1 RETURN child ORDER BY child.createdAt DESC LIMIT 1' );
		$query->setParameter( 'comId', intval( $parentId ) );
		$query->setParameter( 'refType', $type );
		$query->addEntityMapping( 'child', CommentNode::class );
		$res = $query->getOneOrNullResult();

		return ( ! is_null( $res ) ) ? $res[0] : $res;
	}

	/**
	 * @param $refId
	 * @param $type
	 *
	 * @return CommentNode|null
	 */
	public function findLastSibling( $refId, $type ): ?CommentNode {
		$query = $this->entityManager->createQuery(
			'MATCH (comment:Comment)-[:is_comment_of]->(ref) WHERE id(ref) = {refId} AND comment.refType = {refType} and comment.level = 0 RETURN comment ORDER BY comment.order DESC LIMIT 1' );
		$query->setParameter( 'refId', intval( $refId ) );
		$query->setParameter( 'refType', $type );
		$query->addEntityMapping( 'comment', CommentNode::class );
		$res = $query->getOneOrNullResult();

		return ( ! is_null( $res ) ) ? $res[0] : $res;
	}

}