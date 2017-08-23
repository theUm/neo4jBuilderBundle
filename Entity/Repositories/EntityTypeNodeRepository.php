<?php

namespace BuilderBundle\Entity\Repositories;

use BuilderBundle\Entity\EntityTypeNode;
use BuilderBundle\Entity\TypeFieldNode;
use Doctrine\Common\Collections\ArrayCollection;

class EntityTypeNodeRepository extends BaseRepository {
	/*public function findAllOrderedByName()
	{
		//$qrm = new QueryResultMapping(NewsFeed::class, QueryResultMapping::RESULT_MULTIPLE);
		$qrm = new QueryResultMapping(NewsFeed::class, QueryResultMapping::RESULT_MULTIPLE);
		$query = 'MATCH (p:Post) RETURN p as post, timestamp() as timestamp';

		return $this->nativeQuery($query, null, $qrm);
	}*/

	/**
	 * @param EntityTypeNode $type
	 *
	 * @return \GraphAware\Common\Result\Result
	 */
	public function updateEntityType( EntityTypeNode $type ) {
		return $this->getDb()->run( "MATCH (t:EntityType)
            WHERE id(t)={id}
            set t.name = {name}, t.slug = {slug}, t.description = {description}, t.isDataType = {isDataType} return t",
			$this->entityTypeToArray( $type )
		);
	}

	private function entityTypeToArray( EntityTypeNode $entityType, $withoutId = false ) {
		$entityTypeArray = [
			'name'        => $entityType->getName(),
			'slug'        => $entityType->getSlug(),
			'description' => $entityType->getDescription(),
			'isDataType'  => $entityType->isDataType()

		];
		if ( ! $withoutId ) {
			$entityTypeArray['id'] = $entityType->getId();
		}

		return $entityTypeArray;
	}

	public function deleteEntityTypeFieldValuesWithValues( EntityTypeNode $entityTypeNode, array $fields ) {
		//delete related FieldValues
		$this->deleteEntityTypeFieldValues( $entityTypeNode, $fields );
		//delete related EntityTypeFields
		$this->deleteEntityTypeFields( $entityTypeNode, $fields );
	}

	public function deleteEntityTypeFieldValues( EntityTypeNode $entityTypeNode, array $fields ) {
		//delete related FieldValues
		$this->getDb()->run( "MATCH (et:EntityType)-[r:has_field]-(rem:EntityTypeField)-[:is_value_of]-(fv:FieldValue)
                WHERE id(et)={id} and id(rem) in {id_rem}
                DETACH DELETE fv",
			[
				'id'     => $entityTypeNode->getId(),
				'id_rem' => $fields
			] );
	}

	public function deleteEntityTypeFields( EntityTypeNode $entityTypeNode, array $fields ) {
		//delete related EntityTypeFields
		$this->getDb()->run( "MATCH (et:EntityType)-[r:has_field]-(rem:EntityTypeField)
                WHERE id(et)={id} and id(rem) in {id_rem}
                DETACH DELETE rem",
			[
				'id'     => $entityTypeNode->getId(),
				'id_rem' => $fields
			] );
	}

	public function deleteEntityTypeFieldValuesByValue( EntityTypeNode $entityTypeNode, array $fieldTypesAndValues ) {
		foreach ( $fieldTypesAndValues as $fieldTypeId => $values ) {
			//delete specific FieldValues
			$this->getDb()->run( "MATCH (et:EntityType)-[r:has_field]-(rem:EntityTypeField)-[:is_value_of]-(fv:FieldValue)
                WHERE id(et)={id} and id(rem) = {field} and fv.data in {values}
                DETACH DELETE fv",
				[
					'id'     => $entityTypeNode->getId(),
					'field'  => $fieldTypeId,
					'values' => $values
				] );
		}
	}

	public function editEntityTypeFields( array $fields ) {
		foreach ( $fields as $id => $fieldData ) {
			$this->updateFieldTypeNode( $id, $fieldData );
		}
	}

	public function updateFieldTypeNode( $id, array $data ) {
		$dataQueryPart = $this->prepareUpdateQuery( $data, 'etf' );
		$query         = 'MATCH (etf:EntityTypeField) WHERE ID(etf)={id} SET ' . $dataQueryPart['params'];
		//$queryStack->push(
		$this->entityManager->getDatabaseDriver()->run(
			$query,
			array_merge(
				[ 'id' => $id ],
				$dataQueryPart['values']
			) );

	}

	public function createFields( EntityTypeNode $entityTypeNode, array $formData ) {
		foreach ( $formData as $field ) {
			if ( empty( $field['id'] ) ) {
				$dataFields = $this->prepareCreateQuery( $field );
				$this->entityManager->getDatabaseDriver()->run( "MATCH (entityType:EntityType {name:{entityType}})
                MERGE (etf:EntityTypeField {" . $dataFields . "})
                CREATE UNIQUE (etf)-[:has_field]->(entityType)",
					[ 'entityType' => $entityTypeNode->getName(), 'fields' => $dataFields ] );
			}
		}
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return \BuilderBundle\Entity\ObjectNode[] | \Doctrine\Common\Collections\ArrayCollection
	 */
	public function getParentObjects( EntityTypeNode $entityType ) {
		$parentObjects         = new ArrayCollection();
		$entityTypeParentTypes = $entityType->getParents();
		foreach ( $entityTypeParentTypes as $type ) {
			/** @var ArrayCollection $parentObjectsCollection */
			$parentObjectsCollection = $type->getObjects();
			if ( $parentObjectsCollection->count() > 0 ) {
				foreach ( $parentObjectsCollection as $objectNode ) {
					$parentObjects->add( $objectNode );
				}
			}
		}

		return $parentObjects;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return array
	 */
	public function createNode( EntityTypeNode $entityType ) {
		$dataQueryPart = $this->prepareCreateQuery( $this->entityTypeToArray( $entityType, true ) );
		$query         = 'MERGE (et:EntityType {' . $dataQueryPart . '}) return id(et)';

		//$queryStack->push(
		return $this->entityManager->getDatabaseDriver()->run( $query )->firstRecord()->values();
	}

	/**
	 * @param int $nodeId
	 * @param array $parentIds
	 *
	 * @internal param EntityTypeNode $entityType
	 */
	public function linkNodeToParents( int $nodeId, array $parentIds ) {
		$query = 'MATCH (parentET:EntityType), (et:EntityType) WHERE id(parentET) in {parentIds} and id(et) = {id}
                CREATE UNIQUE (et)-[:is_child_of]->(parentET)';
		//$queryStack->push(
		$this->entityManager->getDatabaseDriver()->run( $query, [ 'id' => $nodeId, 'parentIds' => $parentIds ] );
	}

	public function getAllFieldTypeGroups( EntityTypeNode $type ) {
		$uniqueGroups = [];
		/** @var TypeFieldNode $typeField */
		foreach ( $type->getEntityTypeFields()->getIterator() as $typeField ) {
			if ( ! in_array( $typeField->getTabGroup(), $uniqueGroups ) ) {
				$uniqueGroups[] = $typeField->getTabGroup();
			}
		}

		return $uniqueGroups;
	}

	public function findAllWithFieldTypeFile() {
		$query = $this->entityManager->createQuery(
			'MATCH (et:EntityType)-[r:has_field]-(rem:EntityTypeField {fieldType:\'file\'})
            RETURN DISTINCT et'
		);
		$query->addEntityMapping( 'et', EntityTypeNode::class );

		return new ArrayCollection( $query->getResult() );
	}

	/**
	 * @param $isChildsLink bool
	 *
	 * @return string
	 */
	protected function getCreateRelationsQuery( bool $isChildsLink ): string {
		$queryDirection = $this->getQueryDirections( $isChildsLink );
		$leftPart       = $queryDirection['leftPart'];
		$rightPart      = $queryDirection['rightPart'];

		return "MATCH (t:EntityType),(add:EntityType) WHERE id(t)={id} and id(add) in {id_add} CREATE UNIQUE (t)$leftPart-[:is_child_of]-$rightPart(add) return t";
	}

	protected function getDeleteRelationsQuery( bool $isChildsLink ): string {
		$queryDirection = $this->getQueryDirections( $isChildsLink );
		$leftPart       = $queryDirection['leftPart'];
		$rightPart      = $queryDirection['rightPart'];

		return "MATCH (t:EntityType)$leftPart-[r:is_child_of]-$rightPart(rem:EntityType) WHERE id(t)={id} and id(rem) in {id_rem} DELETE r return t";
	}
}