<?php
/**
 * User: share
 * Email: 1337.um@gmail.com
 * Company: Nodeart
 * Date: 19-Dec-16
 * Time: 13:08
 */

namespace BuilderBundle\Services;


use BuilderBundle\Entity\EntityTypeNode;
use BuilderBundle\Entity\ObjectNode;
use GraphAware\Common\Result\Record;
use GraphAware\Neo4j\OGM\EntityManager;

class EntityTypeChildsUnlinker {
	/** @var EntityManager */
	private $nm;
	private $nmDriver;

	public function __construct( EntityManager $nm ) {
		$this->nm       = $nm;
		$this->nmDriver = $nm->getDatabaseDriver();
	}

	/**
	 * @return EntityManager
	 */
	public function getNM() {
		return $this->nm;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return array
	 */
	public function getEntityTypeMultiparentFieldValuesIds( EntityTypeNode $entityType ) {
		$ids     = [];
		$records = $this->nmDriver->run( "
        MATCH (et:EntityType)<-[r2:has_field]-(etf:EntityTypeField)<-[r:is_value_of]
        -(fv:FieldValue)-
        [:is_value_of]->(etf2:EntityTypeField)-[]->(et2:EntityType)
         WHERE id(et) = {etId} AND (etf <> etf2) AND (et <> et2) RETURN DISTINCT id(fv) as id",
			[
				'etId' => $entityType->getId(),
			] )->records();

		/** @var Record $record */
		foreach ( $records as $record ) {
			$ids[] = $record->get( 'id' );
		}

		return $ids;
	}

	/**
	 * @param $entity
	 *
	 * @return EntityTypeChildsUnlinker
	 */
	public function unlinkFieldValues( $entity ) {
		switch ( get_class( $entity ) ) {
			case ( EntityTypeNode::class ): {
				$this->unlinkEntityTypeFieldValues( $entity );
				break;
			}
			case ( ObjectNode::class ): {
				$this->unlinkObjectFieldValues( $entity );
				break;
			}
		}

		return $this;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return EntityTypeChildsUnlinker
	 */
	private function unlinkEntityTypeFieldValues( EntityTypeNode $entityType ) {
		$this->nmDriver->run( "
            MATCH (et:EntityType)<-[r2:has_field]-(etf:EntityTypeField)<-[r:is_value_of]
            -(fv:FieldValue)-
            [:is_value_of]->(etf2:EntityTypeField)-[]->(et2:EntityType)
             WHERE id(et) = {etId} AND (etf <> etf2) AND (et <> et2) DELETE r",
			[
				'etId' => $entityType->getId(),
			] );

		return $this;
	}

	/**
	 * @param ObjectNode $object
	 *
	 * @return EntityTypeChildsUnlinker
	 */
	private function unlinkObjectFieldValues( ObjectNode $object ) {
		$this->nmDriver->run( "
            MATCH (o:Object)<-[r:is_field_of]-(fv:FieldValue)-[:is_field_of]->(o2:Object)
            WHERE id(o) = {etId} AND (o <> o2) DELETE r",
			[
				'etId' => $object->getId(),
			] );

		return $this;
	}


	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return array
	 */
	public function getEntityTypeMultiparentFieldTypeIds( EntityTypeNode $entityType ) {
		$ids     = [];
		$records = $this->nmDriver->run( "
        MATCH (et:EntityType)<-[r2:has_field]-(etf:EntityTypeField)-[:has_type]->(et2:EntityType)
        WHERE id(et) = {etId} AND (etf <> et2) RETURN DISTINCT id(etf) as id",
			[
				'etId' => $entityType->getId(),
			] )->records();

		/** @var Record $record */
		foreach ( $records as $record ) {
			$ids[] = $record->get( 'id' );
		}

		return $ids;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return EntityTypeChildsUnlinker
	 */
	public function unlinkFieldTypes( EntityTypeNode $entityType ) {
		$this->nmDriver->run( "
            MATCH (et:EntityType)<-[r:has_field]-(etf:EntityTypeField)-[:has_type]->(et2:EntityType)
            WHERE id(et) = {etId} AND (etf <> et2) DELETE r",
			[
				'etId' => $entityType->getId(),
			] );

		return $this;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return array
	 */
	public function getEntityTypeMultiparentObjectIds( EntityTypeNode $entityType ) {
		$ids     = [];
		$records = $this->nmDriver->run( "
        MATCH (et:EntityType)<-[r2:has_type]-(o:Object)-[:has_type]->(et2:EntityType)
            WHERE id(et) = {etId} AND (et <> et2) RETURN DISTINCT id(o) as id",
			[
				'etId' => $entityType->getId(),
			] )->records();

		/** @var Record $record */
		foreach ( $records as $record ) {
			$ids[] = $record->get( 'id' );
		}

		return $ids;
	}

	/**
	 * @param EntityTypeNode $entityType
	 *
	 * @return EntityTypeChildsUnlinker
	 */
	public function unlinkObjects( EntityTypeNode $entityType ) {
		$this->nmDriver->run( "
            MATCH (et:EntityType)<-[r:has_type]-(o:Object)-[:has_type]->(et2:EntityType)
            WHERE id(et) = {etId} AND (et <> et2) DELETE r",
			[
				'etId' => $entityType->getId(),
			] );

		return $this;
	}

	public function deleteEntityTypeWithChilds( EntityTypeNode $entity ) {
		$this->nmDriver->run( "
            MATCH (et:EntityType)<-[r2:has_field]-(etf:EntityTypeField)<-[r:is_value_of]-(fv:FieldValue)
            WHERE id(et) = {etId} DETACH DELETE fv,etf",
			[
				'etId' => $entity->getId(),
			] );
		$this->nmDriver->run( "
            MATCH (et:EntityType)<-[r2:has_type]-(o:Object)
            WHERE id(et) = {etId} DETACH DELETE o,et",
			[
				'etId' => $entity->getId(),
			] );
		$this->nmDriver->run( "
            MATCH (et:EntityType)
            WHERE id(et) = {etId} DETACH DELETE et",
			[
				'etId' => $entity->getId(),
			] );
	}

	public function deleteObjectWithChilds( ObjectNode $entity ) {
		$this->nmDriver->run( "
            MATCH (o:Object) WHERE id(o) = {oId} OPTIONAL MATCH (o)<-[:is_field_of]-(fv:FieldValue)
            DETACH DELETE fv,o",
			[
				'oId' => $entity->getId(),
			] );
	}

//    /**
//     * @param ObjectNode $object
//     * @return EntityTypeChildsUnlinker
//     */
//    public function unlinkObjectRelatedObjects(ObjectNode $object)
//    {
//        $this->getNMDriver()->run("
//            MATCH (o:Object)<-[r:is_child_of]-(oC:Object)-[r2:is_child_of]->(oP:Object)
//            WHERE id(o) = {oId} AND (o <> oP) DELETE r",
//            [
//                'oId' => $object->getId(),
//            ]);
//        return $this;
//    }

}