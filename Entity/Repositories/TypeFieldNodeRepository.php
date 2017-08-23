<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use Nodeart\BuilderBundle\Entity\TypeFieldNode;

class TypeFieldNodeRepository extends BaseRepository {

	/**
	 * @param string $entityType
	 * @param string $slug
	 *
	 * @return array
	 */
	public function findTypeFieldBySlug( string $entityType, string $slug ) {
		$entityTypeFieldsQuery = $this->entityManager->createQuery(
			'MATCH (etf:EntityTypeField)-[:has_field]-(et:EntityType) 
            WHERE et.slug = {etSlug} AND etf.slug = {etfSlug} RETURN etf LIMIT 1'
		);
		$entityTypeFieldsQuery->addEntityMapping( 'etf', TypeFieldNode::class );
		$entityTypeFieldsQuery->setParameter( 'etSlug', $entityType );
		$entityTypeFieldsQuery->setParameter( 'etfSlug', $slug );
		$res = $entityTypeFieldsQuery->execute();

		return empty( $res ) ? null : $res[0];
	}

	public function findEntityTypeFieldsByType( string $entityType ) {
		$entityTypeFieldsQuery = $this->entityManager->createQuery(
			'MATCH (etf:EntityTypeField)-[:has_field]-(et:EntityType) 
            WHERE et.slug = {etSlug} RETURN etf ORDER BY etf.order'
		);
		$entityTypeFieldsQuery->addEntityMapping( 'etf', TypeFieldNode::class );
		$entityTypeFieldsQuery->setParameter( 'etSlug', $entityType );
		$res = $entityTypeFieldsQuery->execute();

		return empty( $res ) ? [] : $res;
	}


	protected function getCreateRelationsQuery( bool $isChildsLink ): string {
		// TODO: Implement getCreateRelationsQuery() method.
		return null;
	}

	protected function getDeleteRelationsQuery( bool $isChildsLink ): string {
		// TODO: Implement getDeleteRelationsQuery() method.
		return null;
	}
}