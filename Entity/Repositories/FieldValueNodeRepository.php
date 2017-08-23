<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Symfony\Component\Form\Exception\LogicException;

class FieldValueNodeRepository extends BaseRepository {

	/**
	 * @todo: make universal creater/updater
	 *
	 * @param array $values
	 * @param int $fieldTypeId
	 * @param ObjectNode $object
	 */
	public function createFieldValues( $values, int $fieldTypeId, ObjectNode $object ) {
		$query = 'MATCH (o:Object)-[:has_type]->
                    (et:EntityType)<-[:has_field]-(etf:EntityTypeField)
                WHERE id(o) = {oId} AND id(et) = {etId} AND id(etf) = {etfId}
                MERGE (etf)<-[:is_value_of]-(fv:FieldValue {%params%})
                CREATE UNIQUE (fv)-[:is_field_of]->(o)';
		foreach ( $values as $index => $valueNode ) {
			if ( ! ( $valueNode instanceof FieldValueNode ) ) {
				throw  new LogicException( 'Invalid form transforming for ' . get_class( $valueNode ) . '. ' . FieldValueNode::class . ' needed' );
			}
			if ( ! is_null( $valueNode ) ) {
				$this->createSingleValue( $valueNode, $fieldTypeId, $object, $query );
			}
		}
	}

	/**
	 * @param FieldValueNode $valueNode
	 * @param int $fieldTypeId
	 * @param ObjectNode $object
	 * @param string $query
	 */
	private function createSingleValue( FieldValueNode $valueNode, int $fieldTypeId, ObjectNode $object, string $query ) {
		if ( ! empty( $valueNode ) ) {
			$dataQueryPart = $this->getParamNamesQueryPart( $valueNode );
			$query         = str_replace( '%params%', $dataQueryPart['params'], $query );
			$this->entityManager->getDatabaseDriver()->run( $query,
				array_merge(
					$dataQueryPart['values'],
					[ 'etId' => $object->getEntityType()->getId(), 'oId' => $object->getId(), 'etfId' => $fieldTypeId ]
				)
			);
		}
	}

	private function getParamNamesQueryPart( FieldValueNode $valueNode, $forSetQueryPart = false, $prefix = 'fv.' ) {
		$dataQueryPart = [ 'params' => '', 'values' => '' ];
		if ( ! empty( $valueNode->getFileName() ) ) {
			//return $dataQueryPart;
			if ( $forSetQueryPart ) {
				$dataQueryPart['params'] = $prefix . 'fileName={fileName}, ' . $prefix . 'originalFileName={originalFileName}, ' . $prefix . 'path={path}, ' . $prefix . 'webPath={webPath}, ' . $prefix . 'mimeType={mimeType}, ' . $prefix . 'createdAt={createdAt}';
			} else {
				$dataQueryPart['params'] = 'fileName:{fileName}, originalFileName:{originalFileName}, path:{path}, webPath:{webPath}, mimeType:{mimeType}, createdAt:{createdAt}';
			}
			$dataQueryPart['values'] = [
				'fileName'         => $valueNode->getFileName(),
				'originalFileName' => $valueNode->getOriginalFileName(),
				'path'             => $valueNode->getPath(),
				'webPath'          => $valueNode->getWebPath(),
				'createdAt'        => $valueNode->getCreatedAt()->getTimestamp(),
				'mimeType'         => $valueNode->getMimeType()
			];
		} else {
			if ( $forSetQueryPart ) {
				$dataQueryPart['params'] = $prefix . 'data={data}';
			} else {
				$dataQueryPart['params'] = 'data:{data}';
			}
			$dataQueryPart['values'] = [ 'data' => $valueNode->getData() ];
		}

		return $dataQueryPart;
	}

	/**
	 * @param array $values
	 * @param int $fieldTypeId
	 * @param ObjectNode $object
	 */
	public function updateFieldValuesOrder( $values, int $fieldTypeId, ObjectNode $object ) {
		$query = 'MATCH (fv:FieldValue {%params%})-[r:is_field_of]->(o:Object)-[:has_type]->
                    (et:EntityType)<-[:has_field]-(etf:EntityTypeField)--(fv)
                WHERE id(o) = {oId} AND id(et) = {etId} AND id(etf) = {etfId}
                set r.order = {order}';
		foreach ( $values as $order => $valueNode ) {
			if ( ! ( $valueNode instanceof FieldValueNode ) ) {
				throw  new LogicException( 'Invalid form transforming for ' . get_class( $valueNode ) . '. ' . FieldValueNode::class . ' needed' );
			}
			if ( ! is_null( $valueNode ) ) {
				$this->updateSingleValueOrder( $valueNode, $order, $fieldTypeId, $object, $query );
			}
		}
	}

	/**
	 * @param FieldValueNode $valueNode
	 * @param int $order
	 * @param int $fieldTypeId
	 * @param ObjectNode $object
	 * @param string $query
	 */
	private function updateSingleValueOrder( FieldValueNode $valueNode, int $order, int $fieldTypeId, ObjectNode $object, string $query ) {
		if ( ! empty( $valueNode ) ) {
			$dataQueryPart = $this->getParamNamesQueryPart( $valueNode );
			$query         = str_replace( '%params%', $dataQueryPart['params'], $query );
			$this->entityManager->getDatabaseDriver()->run( $query,
				array_merge(
					$dataQueryPart['values'],
					[
						'etId'  => $object->getEntityType()->getId(),
						'oId'   => $object->getId(),
						'etfId' => $fieldTypeId,
						'order' => $order
					]
				)
			);
		}
	}

	/**
	 * @param FieldValueNode $valueNode
	 * @param bool $removeFile
	 *
	 * @return \GraphAware\Common\Result\Result|null
	 * @internal param int $fieldTypeId
	 * @internal param ObjectNode $object
	 * @internal param string $query
	 */
	public function detachSingleFileValue( FieldValueNode $valueNode, bool $removeFile = true ) {
		if ( $removeFile ) {
			$this->removeUploadedFile( $valueNode->getPath() );
		}

		return $this->entityManager->getDatabaseDriver()->run(
			'MATCH (fv:FieldValue) where id(fv) = {id} DETACH DELETE fv',
			[ 'id' => $valueNode->getId() ]
		);
	}

	private function removeUploadedFile( $path ) {
		try {
			unlink( $path );
		} catch ( \Exception $exception ) {
			//@todo: handle file deletion exception
		}
	}

	/**
	 * @todo: make universal creater/updater
	 *
	 * @param array $values
	 * @param int $fieldTypeId
	 * @param ObjectNode $object
	 */
	public function deleteFieldValues( $values, int $fieldTypeId, ObjectNode $object ) {
		if ( ! empty( $values ) ) {
			$valuesIds = [];
			/** @var FieldValueNode $value */
			foreach ( $values as $value ) {
				$this->removeUploadedFile( $value->getPath() . DIRECTORY_SEPARATOR . $value->getFileName() );
				$valuesIds[] = $value->getId();
			}

			$removeRelationQuery = 'MATCH (o2:Object)<-[:is_field_of]-(fv:FieldValue)-[obj_field_rel:is_field_of]->(o:Object)-[:has_type]->(et:EntityType)
                        <-[:has_field]-(etf:EntityTypeField)-[etfr:is_value_of]-(fv)
                   WHERE id(o) = {oId} AND (o <> o2) AND id(etf)= {etfId} AND id(fv) in {ids} 
                   DELETE obj_field_rel';
			$detachRemoveQuery   = 'MATCH (fv:FieldValue)-[:is_field_of]->(o:Object)-[:has_type]->(et:EntityType)
                        <-[:has_field]-(etf:EntityTypeField)-[etfr:is_value_of]-(fv)
                   WHERE id(o) = {oId} AND id(etf)= {etfId} AND id(fv) in {ids}
                   DETACH DELETE fv';
			$this->entityManager->getDatabaseDriver()->run(
				$removeRelationQuery,
				[
					'oId'   => $object->getId(),
					'etfId' => $fieldTypeId,
					'ids'   => $valuesIds
				] );
			$this->entityManager->getDatabaseDriver()->run(
				$detachRemoveQuery,
				[
					'oId'   => $object->getId(),
					'etfId' => $fieldTypeId,
					'ids'   => $valuesIds
				] );
		}
	}

	public function getMediaDropdownChoices( int $entityTypeId, int $objectId, int $fieldTypeId = null ) {
		$fieldTypeIdFilter = ( is_null( $fieldTypeId ) ) ? '' : ' AND id(etf) = ' . $fieldTypeId;
		$query             = $this->entityManager->createQuery(
			'MATCH (etf:EntityTypeField {fieldType:"file"})-->(et:EntityType)<--(o:Object) WHERE id(et) = {entityTypeId} AND  id(o) = {objId} ' . $fieldTypeIdFilter . '
                OPTIONAL MATCH (o)<--(fv:FieldValue)-->(etf)
            RETURN id(etf) as id, etf.name as name, collect(fv.originalFileName) as files'
		);
		$query->setParameter( 'entityTypeId', $entityTypeId );
		$query->setParameter( 'objId', $objectId );
		$result = [];
		foreach ( $query->getResult() as $row ) {
			$name     = $row['name'] . ( ! empty( $row['files'] ) ? ' (' . join( ',', $row['files'] ) . ')' : '' );
			$result[] = [
				'name'  => $name,
				'value' => $row['id'],
				'text'  => $name,
			];
		}

		return $result;
	}

	public function findByIds( array $ids ) {
		$query = $this->entityManager->createQuery( 'MATCH (fv:FieldValue) WHERE id(fv) IN {ids} RETURN fv' );
		$query->setParameter( 'ids', $ids );
		$query->addEntityMapping( 'fv', FieldValueNode::class );

		return $query->execute();
	}

	public function findObjectValuesByType( ObjectNode $object, TypeFieldNode $tf ) {
		$query = $this->entityManager->createQuery( 'MATCH (etf:EntityTypeField)<--(fv:FieldValue)-->(o:Object) WHERE id(o) = {oId} and id(etf) = {etfId} RETURN fv' );
		$query->setParameter( 'oId', $object->getId() );
		$query->setParameter( 'etfId', $tf->getId() );
		$query->addEntityMapping( 'fv', FieldValueNode::class );

		return $query->execute();
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