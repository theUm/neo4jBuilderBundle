<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\OGM\Repository\BaseRepository;
use Nodeart\BuilderBundle\Entity\CommentNode;

class CommentNodeRepository extends BaseRepository {


	public function findCommentsByRefIdAndType( $nodeId, $type, $limit = 10 ) {
		$query = $this->entityManager->createQuery( 'MATCH (com:Comment)-->(ref) WHERE id(ref) = {refId} and com.refType = {refType} AND com.createdAt > 0 RETURN com ORDER BY com.order LIMIT {limit}' );
		$query->setParameter( 'refId', intval( $nodeId ) );
		$query->setParameter( 'refType', $type );
		$query->setParameter( 'limit', intval( $limit ) );
		$query->addEntityMapping( 'com', CommentNode::class );

		return $query->execute();
	}

	/**
	 * @param $parentId
	 * @param $type
	 * @param $level
	 *
	 * @return CommentNode|null
	 */
	public function findLastCommentChild( $parentId, $type, $level ): ?CommentNode {
		$query = $this->entityManager->createQuery(
			'MATCH (com:Comment)<-[:is_child_of]-(child:Comment) WHERE id(com) = {comId} AND com.refType = {refType} AND child.level = {level} RETURN child ORDER BY child.createdAt LIMIT 1' );
		$query->setParameter( 'comId', intval( $parentId ) );
		$query->setParameter( 'refType', $type );
		$query->setParameter( 'level', $level );
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
			'MATCH (com:Comment)-[:is_comment_of]->(ref) WHERE id(ref) = {refId} AND com.refType = {refType} and com.level = 0 RETURN com ORDER BY com.order DESC LIMIT 1' );
		$query->setParameter( 'refId', intval( $refId ) );
		$query->setParameter( 'refType', $type );
		$query->addEntityMapping( 'com', CommentNode::class );
		$res = $query->getOneOrNullResult();

		return ( ! is_null( $res ) ) ? $res[0] : $res;
	}

}