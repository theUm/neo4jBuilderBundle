<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\OGM\Query;
use GraphAware\Neo4j\OGM\Repository\BaseRepository;
use Nodeart\BuilderBundle\Entity\CommentNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class CommentNodeRepository extends BaseRepository {

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

	/**
	 * @param int $refComId
	 *
	 * @return array|null
	 *
	 */
	public function findMoreChildComments( int $refComId ): ?array {
		$query = $this->entityManager->createQuery(
			'MATCH (refCom:Comment)-[:is_comment_of]->(o) WHERE id(refCom) = {refComId}
			 MATCH (refCom)-[:is_child_of]->(parentCom:Comment)<-[:is_child_of]-(sibling:Comment)-[:commented]->(user:User)
			    WHERE sibling.createdAt < refCom.createdAt AND sibling.refType = refCom.refType
			    AND ( sibling.reports < 5  OR sibling.reports is null)
		     RETURN collect({comment:sibling, user:user}) as comments LIMIT 10' );
		$query->setParameter( 'refComId', intval( $refComId ) );
		$query->addEntityMapping( 'comment', CommentNode::class );
		$query->addEntityMapping( 'user', UserNode::class );
		$query->addEntityMapping( 'comments', null, Query::HYDRATE_MAP_COLLECTION );
		$res = $query->getResult();

		return ( ! is_null( $res ) ) ? $res[0] : $res;
	}


	/**
	 * Returns structure of first level comments + users ordered by createdAt DESC  and some of the related
	 * second level comments ordered by createdAt ASC
	 *
	 * @param int $refComId
	 *
	 * @return array|mixed
	 */
	public function findMoreParentComments( int $refComId ) {
		$query = $this->entityManager->createQuery( '
		MATCH (refCom:Comment)-[:is_comment_of]-(o) WHERE id(refCom) = {refCom}
		MATCH (o)<-[:is_comment_of]-(comment:Comment)-[]->(user:User) 
			WHERE comment.level=0 AND comment.refType = refCom.refType AND refCom.createdAt > comment.createdAt
			AND ( comment.reports < 5  OR comment.reports is null)
			WITH comment, user ORDER BY comment.createdAt DESC limit 10
		OPTIONAL MATCH (comment)<-[:is_child_of]-(child:Comment)-->(childUser:User)
            WITH comment, user, child, childUser ORDER BY child.createdAt DESC limit 100
		RETURN comment, user, count(child) as count,
			CASE childUser 
				WHEN NOT exists(childUser.name) 
				THEN [] 
				ELSE collect({comment:child, user:childUser}) 
			END AS childs
        ORDER BY comment.createdAt DESC' );

		$query->setParameter( 'refCom', intval( $refComId ) );
		$query->addEntityMapping( 'comment', CommentNode::class );
		$query->addEntityMapping( 'user', UserNode::class );
		$query->addEntityMapping( 'childs', null, Query::HYDRATE_MAP_COLLECTION );

		return $query->getResult();
	}

	/**
	 * @param int $oId
	 * @param int $userId
	 *
	 * @return array|null
	 */
	public function findObjectUserReactions( int $oId, int $userId ): ?array {
		$query = $this->entityManager->createQuery(
			'MATCH (u:User)-[r:Reaction]-(com:Comment)-[]-(o) where id(u) = {uId} and id(o) = {oId} 
			 RETURN collect(case r.liked when true then id(com) else null END) as liked, 
			 collect(case r.disliked when true then id(com) else null END) as disliked,
			 collect (case r.reported when true then id(com) else null END) as reported' );
		$query->setParameter( 'oId', intval( $oId ) );
		$query->setParameter( 'uId', intval( $userId ) );
		$res = $query->getResult();

		return ( ! is_null( $res ) ) ? $res[0] : $res;
	}

	/**
	 * @param int $status
	 * @param array $ids
	 *
	 * @return array|null updated nodes
	 */
	public function updateCommentStatuses( int $status, array $ids ):?array {
		$query = $this->entityManager->createQuery( 'MATCH (n:Comment) WHERE id (n) in {ids} SET n.status = {status} RETURN collect(id(n)) as ids' );
		$query->setParameter( 'status', $status );
		$query->setParameter( 'ids', $ids );
		$res = $query->getOneResult();

		return $res;
	}

}