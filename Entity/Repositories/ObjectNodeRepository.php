<?php

namespace Nodeart\BuilderBundle\Entity\Repositories;

use GraphAware\Neo4j\OGM\Query;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Entity\UserNode;

class ObjectNodeRepository extends BaseRepository
{

    public function isObjectWithValueExists(EntityTypeNode $entityTypeNode, $paramNameCount, $paramValue)
    {
        return ($this->countObjectByType($entityTypeNode, $paramNameCount, $paramValue) > 0);
    }

    public function countObjectByType(EntityTypeNode $entityTypeNode, $paramNameCount, $paramValue)
    {
        $res = $this->entityManager->getDatabaseDriver()->run(
            'MATCH (type:EntityType)<-[:has_type*0..1]-(o:Object {' . $paramNameCount . ':{paramValue}}) WHERE id(type) = {etId} RETURN count(o)',
            ['etId' => $entityTypeNode->getId(), 'paramValue' => $paramValue]);

        return $res->firstRecord()->values()[0];
    }

    /**
     * @param ObjectNode $object
     * @param EntityTypeNode $entityType
     * @param array $parentIds
     *
     * @return ObjectNode $object
     */
    public function createObjectNode(ObjectNode $object, EntityTypeNode $entityType, array $parentIds = [])
    {

        $this->entityManager->clear();
        $this->entityManager->persist($object);
        $this->entityManager->flush();
        $objectId = $object->getId();

        //lets link created node to relateds. Its in separate query because we cant "create unique (node{params:param})--(linked:Linked)" cuz we still relay on ID(node)
        $parentIdsPart1 = $parentIdsPart2 = $parentIdsPart3 = '';
        // this part is used to link object to other objects in one query
        if (!empty($parentIds)) {
            $parentIdsPart1 = ', (parentObj:Object)';
            $parentIdsPart2 = 'AND id(parentObj) in {parentIds}';
            $parentIdsPart3 = ',(obj)-[:is_child_of]->(parentObj)';
        }
        $linkQuery = 'MATCH (obj:Object), (et:EntityType) ' . $parentIdsPart1 . '
                                WHERE id(et)= {entityTypeId} ' . $parentIdsPart2 . ' AND id(obj) = {oId}
                              CREATE UNIQUE
                                (obj)-[:has_type]->(et) ' . $parentIdsPart3 . ' return obj';
        $this->entityManager->getDatabaseDriver()->run(
            $linkQuery,
            ['entityTypeId' => $entityType->getId(), 'parentIds' => $parentIds, 'oId' => $objectId]
        );

        $object->setEntityType($entityType);

        return $object;
    }

    public function updateObjectNodeData($id, array $data)
    {
        $dataQueryPart = $this->prepareUpdateQuery($data, 'o');
        $query = 'MATCH (o:Object) WHERE ID(o)={id} SET ' . $dataQueryPart['params'];
        //$queryStack->push(
        $this->entityManager->getDatabaseDriver()->run(
            $query,
            array_merge(
                ['id' => $id],
                $dataQueryPart['values']
            ));
    }

    /**
     * Seeks of all object`s fields and values
     *
     * @param ObjectNode $objectNode
     *
     * @return array|mixed
     */
    public function getFields(ObjectNode $objectNode)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField)-[:has_field]->(et:EntityType)<-[:has_type]-(o:Object)
            WHERE id(o) = {oId}
            OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf)
            WITH rel, etf, fv ORDER BY etf.order, rel.order
            WITH etf, collect(fv) as val
            RETURN etf as type, val'
        );
        $entityTypeFieldsQuery->addEntityMapping('type', TypeFieldNode::class);
        $entityTypeFieldsQuery->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $entityTypeFieldsQuery->setParameter('oId', $objectNode->getId());
        $result = $entityTypeFieldsQuery->execute();
        $objectNode->setFields($result);

        return $result;
    }

    /**
     * Seeks of all object`s fields and values
     *
     * @param ObjectNode $objectNode
     *
     * @return array|mixed
     */
    public function getFieldsStructWithSlug(ObjectNode $objectNode)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField)-[:has_field]->(et:EntityType)<-[:has_type]-(o:Object)
            WHERE id(o) = {oId}
            OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf)
            WITH rel, etf, fv ORDER BY etf.order, rel.order
            WITH etf, collect(fv) as val
            RETURN etf.slug as slug, {fieldType: etf, val:val} as field'
        );
        $entityTypeFieldsQuery->addEntityMapping('fieldType', TypeFieldNode::class);
        $entityTypeFieldsQuery->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $entityTypeFieldsQuery->addEntityMapping('field', null, Query::HYDRATE_MAP);
        $entityTypeFieldsQuery->setParameter('oId', $objectNode->getId());
        $result = $entityTypeFieldsQuery->execute();

        $rearrangedResult = [];
        foreach ($result as $fieldType) {
            $rearrangedResult[$fieldType['slug']] = $fieldType['field'];
        }

        return $rearrangedResult;
    }

    /**
     * @param ObjectNode $objectNode
     *
     * @return array|mixed
     */
    public function getFieldTypes(ObjectNode $objectNode)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (o:Object)-[:has_type]->(et:EntityType)<-[:has_field]-(etf:EntityTypeField) WHERE id(o) = {oId} RETURN etf ORDER BY etf.order'
        );
        $entityTypeFieldsQuery->addEntityMapping('etf', TypeFieldNode::class);
        $entityTypeFieldsQuery->setParameter('oId', $objectNode->getId());

        return $entityTypeFieldsQuery->execute();
    }

    /**
     * @param string $parentTypeSlug
     * @param string $parentSlug
     * @param string $childTypeSlug
     * @param int $limit
     * @param int $skip
     * @param string $isDataType
     *
     * @return array
     */
    public function findChildObjectsByParent(string $parentTypeSlug, string $parentSlug, string $childTypeSlug, int $limit = 10, int $skip = 0, $isDataType = 'both', $ignoredIds = [])
    {


        $isDataTypePart = ($isDataType == 'both') ? '' : ('AND type.isDataType = ' . ($isDataType) ? 'true' : 'false');
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (type:EntityType)<-[:has_type]-(o:Object)-[:is_child_of]->(parent:Object)-[:has_type]->(parentType:EntityType)
        	    WHERE parentType.slug = {ptSlug} AND parent.slug = {pSlug} AND type.slug = {tSlug} ' . $isDataTypePart . ' AND NOT id(o) IN {ids} 
        	    RETURN o SKIP {skip} LIMIT {limit}'
        );
        $entityTypeFieldsQuery->addEntityMapping('o', ObjectNode::class);
        $entityTypeFieldsQuery->setParameter('ptSlug', $parentTypeSlug);
        $entityTypeFieldsQuery->setParameter('pSlug', $parentSlug);
        $entityTypeFieldsQuery->setParameter('tSlug', $childTypeSlug);
        $entityTypeFieldsQuery->setParameter('limit', $limit);
        $entityTypeFieldsQuery->setParameter('skip', $skip);
        $entityTypeFieldsQuery->setParameter('ids', $ignoredIds);
        return $entityTypeFieldsQuery->execute();
    }


    /**
     * @param ObjectNode $object
     * @param int $limit
     * @param int $skip
     *
     * @param null $parentObjectSlug
     * @param array $valuesFilters array of  ['etf'=>'string', 'vals'=>[FieldValueNode]]
     * @return array
     * @throws \Exception
     */
    public function findObjectSiblingsWithFields(ObjectNode $object, int $limit = 10, int $skip = 0, $parentObjectSlug = null, $valuesFilters = [])
    {
        $siblingsQuery = $this->entityManager->createQuery();

        $linkToSingleParentQueryString = (is_null($parentObjectSlug)) ? '' : '-[:is_child_of]->(parentObject:Object)';
        $filterLinkToSingleParentQueryString = (is_null($parentObjectSlug)) ? '' : ' AND parentObject.slug = {parentObjectSlug}';

        if (!empty($valuesFilters) && isset($valuesFilters['etf']) && isset($valuesFilters['vals'])) {

            $comparator = (isset($valuesFilters['cmp'])) ? $valuesFilters['cmp'] : "IN";
            $valuesFilterQueryString = "MATCH (o)<-[:is_field_of]-(filterFV:FieldValue)-[:is_value_of]->(filterETF:EntityTypeField)
                WHERE filterETF.slug = {filterETFSlug} AND filterFV.data $comparator {filterFVs}";

            // array of values
            if (is_array($valuesFilters['vals'])) {
                $vals = [];
                foreach ($valuesFilters['vals'] as $val) {
                    $vals[] = ($val instanceof FieldValueNode) ? $val->getData() : $val;
                }
            } else {
                // single value. transform to numeric if possible
                $vals = is_numeric($valuesFilters['vals']) ? intval($valuesFilters['vals']) : $valuesFilters['vals'];
            }

            if ($valuesFilters['vals']) {
                $siblingsQuery->setParameter('filterETFSlug', $valuesFilters['etf']);
                $siblingsQuery->setParameter('filterFVs', $vals);
            }
        } else {
            $valuesFilterQueryString = '';
        }

        $cql =
            'MATCH (etf:EntityTypeField)--(type:EntityType)<-[:has_type]-(o:Object)' . $linkToSingleParentQueryString . '
        	        WHERE id(o) <> {oId} AND type.slug = {slug} ' . $filterLinkToSingleParentQueryString . '
        	        ' . $valuesFilterQueryString . '
        	    OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf)
        	    OPTIONAL MATCH (childEtf:EntityTypeField)-[:has_field]->(childEt:EntityType)<-[:has_type]-(childO:Object)
		            WITH etf, o, collect(DISTINCT fv) as val ORDER BY o.createdAt
		            RETURN o as object, collect({etfSlug:etf.slug, valsByFields:{fieldType:etf, val:val}}) as objectFields SKIP {skip} LIMIT {limit}';


        $siblingsQuery->setCQL($cql);
        $siblingsQuery->addEntityMapping('object', ObjectNode::class);
        $siblingsQuery->addEntityMapping('fieldType', TypeFieldNode::class);
        $siblingsQuery->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $siblingsQuery->addEntityMapping('valsByFields', null, Query::HYDRATE_MAP);
        $siblingsQuery->addEntityMapping('objectFields', null, Query::HYDRATE_MAP_COLLECTION);
        $siblingsQuery->addEntityMapping('objects', null, Query::HYDRATE_MAP_COLLECTION);

        $siblingsQuery->setParameter('slug', $object->getEntityType()->getSlug());
        $siblingsQuery->setParameter('oId', $object->getId());
        $siblingsQuery->setParameter('limit', $limit);
        $siblingsQuery->setParameter('skip', $skip);
        $siblingsQuery->setParameter('parentObjectSlug', $parentObjectSlug);
        return $siblingsQuery->execute();
    }

    /**
     * Fetches slice of DB - all or provided child Objects, fields, field values, grouped by EntityType and other nested groups
     * I`m so so so sorry
     *
     * @param string $parentTypeSlug
     * @param string $parentSlug
     * @param array $childTypeSlugs
     * @param int $limit
     * @param int $skip
     * @param string $isDataType
     *
     * @return array
     */
    public function findMultipleChildObjectsByParent(string $parentTypeSlug, string $parentSlug, array $childTypeSlugs = [], int $limit = 10, int $skip = 0, $isDataType = 'both')
    {
        $isDataTypePart = ($isDataType == 'both') ? '' : ('AND type.isDataType = ' . ($isDataType) ? 'true' : 'false');
        $childTypeSlugsPart = (empty($childTypeSlugs)) ? '' : 'AND type.slug in {tSlug}';
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField)--(type:EntityType)<-[:has_type]-(o:Object)-[:is_child_of]->(parent:Object)-[:has_type]->(parentType:EntityType)
        	        WHERE parentType.slug = {ptSlug} AND parent.slug = {pSlug} ' . $isDataTypePart . ' ' . $childTypeSlugsPart . ' 
        	    OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf)
		            WITH type, rel, etf, o, collect(fv) as val ORDER BY etf.order, rel.order
		            WITH type, {object:o, objectFields:collect({etfSlug:etf.slug, valsByFields:{fieldType:etf, val:val}})} as fieldsByObject
	            RETURN type.slug as slug, type, collect(fieldsByObject) as objects'
        );

        $entityTypeFieldsQuery->addEntityMapping('type', EntityTypeNode::class);
        $entityTypeFieldsQuery->addEntityMapping('object', ObjectNode::class);
        $entityTypeFieldsQuery->addEntityMapping('fieldType', TypeFieldNode::class);
        $entityTypeFieldsQuery->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $entityTypeFieldsQuery->addEntityMapping('valsByFields', null, Query::HYDRATE_MAP);
        $entityTypeFieldsQuery->addEntityMapping('objectFields', null, Query::HYDRATE_MAP_COLLECTION);
        $entityTypeFieldsQuery->addEntityMapping('objects', null, Query::HYDRATE_MAP_COLLECTION);

        $entityTypeFieldsQuery->setParameter('ptSlug', $parentTypeSlug);
        $entityTypeFieldsQuery->setParameter('pSlug', $parentSlug);
        $entityTypeFieldsQuery->setParameter('tSlug', $childTypeSlugs);
        $entityTypeFieldsQuery->setParameter('limit', $limit);
        $entityTypeFieldsQuery->setParameter('skip', $skip);
        $result = $entityTypeFieldsQuery->execute();

        $restructuredArray = [];
        foreach ($result as $entityTypeStruct) {
            $restructuredArray[$entityTypeStruct['slug']] = ['type' => $entityTypeStruct['type'], 'objects' => []];
            foreach ($entityTypeStruct['objects'] as $objectArray) {
                $restructuredArray[$entityTypeStruct['slug']]['objects'][$objectArray['object']->getId()] = ['object' => $objectArray['object'],
                    'objectFields' => []
                ];
                foreach ($objectArray['objectFields'] as $fieldArray) {
                    $restructuredArray[$entityTypeStruct['slug']]['objects'][$objectArray['object']->getId()]['objectFields'][$fieldArray['etfSlug']] = $fieldArray['valsByFields'];
                }
            }
        }

        return $restructuredArray;
    }

    /**
     * Finds parent objects of specific type that have specific value
     *
     * @param $parentEntityType
     * @param $entityType
     * @param $entityTypeField
     * @param $value
     * @param int $limit
     * @param int $skip
     *
     * @return array
     */
    public function findParentObjectsByValue($parentEntityType, $entityType, $entityTypeField, $value, int $limit = 10, int $skip = 0)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (fv:FieldValue {data:{data}})-[:is_value_of]->(etf:EntityTypeField {slug:{etfSlug}})-[:has_field]
                ->(et:EntityType {slug:{etSlug}})<-[:has_type]-(o:Object)<-[:is_field_of]-(fv)
             MATCH (o)-[:is_child_of]-(parentObj:Object)-[:has_type]-(pet:EntityType {slug:{parentEtSlug}})
             RETURN parentObj SKIP {skip} LIMIT {limit}'
        );
        $entityTypeFieldsQuery->addEntityMapping('parentObj', ObjectNode::class);
        $entityTypeFieldsQuery->setParameter('parentEtSlug', $parentEntityType);
        $entityTypeFieldsQuery->setParameter('etSlug', $entityType);
        $entityTypeFieldsQuery->setParameter('etfSlug', $entityTypeField);
        $entityTypeFieldsQuery->setParameter('data', $value);
        $entityTypeFieldsQuery->setParameter('limit', $limit);
        $entityTypeFieldsQuery->setParameter('skip', $skip);

        return $entityTypeFieldsQuery->execute();
    }

    /**
     * @param string $parentTypeSlug
     * @param string $parentSlug
     * @param string $childTypeSlug
     * @param int $limit
     * @param int $skip
     * @param string $isDataType
     *
     * @return array
     */
    public function getChildObjectsValsByParent(string $parentTypeSlug, string $parentSlug, string $childTypeSlug, int $limit = 10, int $skip = 0, $isDataType = 'both')
    {
        $isDataTypePart = ($isDataType == 'both') ? '' : ('AND type.isDataType = ' . ($isDataType) ? 'true' : 'false');
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField)-[:has_field]->(type:EntityType)<-[:has_type]-(o:Object)-[:is_child_of]
        		->(parent:Object)-[:has_type]->(parentType:EntityType)
        	WHERE parentType.slug = {ptSlug} AND parent.slug = {pSlug} AND type.slug = {tSlug} ' . $isDataTypePart . ' 
        	OPTIONAL MATCH (etf)<-[:is_value_of]-(fv)-[rel:is_field_of]->(o)
        	WITH o, etf, fv, rel ORDER BY etf.order, rel.order
            WITH o, {fieldType:etf, vals: collect(fv)} as fieldValuesMap
            RETURN o, collect(fieldValuesMap) as objectFields SKIP {skip} LIMIT {limit}'
        );
        $entityTypeFieldsQuery->addEntityMapping('o', ObjectNode::class);
        $entityTypeFieldsQuery->addEntityMapping('fieldValuesMap', null, Query::HYDRATE_MAP);
        $entityTypeFieldsQuery->addEntityMapping('objectFields', null, Query::HYDRATE_MAP_COLLECTION);
        $entityTypeFieldsQuery->addEntityMapping('fieldType', TypeFieldNode::class);
        $entityTypeFieldsQuery->addEntityMapping('vals', FieldValueNode::class, Query::HYDRATE_COLLECTION);

        $entityTypeFieldsQuery->setParameter('ptSlug', $parentTypeSlug);
        $entityTypeFieldsQuery->setParameter('pSlug', $parentSlug);
        $entityTypeFieldsQuery->setParameter('tSlug', $childTypeSlug);
        $entityTypeFieldsQuery->setParameter('limit', $limit);
        $entityTypeFieldsQuery->setParameter('skip', $skip);

        return $entityTypeFieldsQuery->execute();
    }

    /**
     * Seeks of all object`s fields and values by field slugs
     *
     * @param ObjectNode $objectNode
     * @param array $slugs
     *
     * @return array|mixed
     */
    public function getFieldsBySlugs(ObjectNode $objectNode, array $slugs)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField)-[:has_field]->(et:EntityType)<-[:has_type]-(o:Object)
                WHERE id(o) = {oId} AND etf.slug in {slugs}
            OPTIONAL MATCH (o)<-[rel:is_field_of]-(fv:FieldValue)-[:is_value_of]->(etf)
            WITH etf, fv ORDER BY rel.order, etf.order
            RETURN etf as type , collect(fv) as val'
        );
        $entityTypeFieldsQuery->addEntityMapping('type', TypeFieldNode::class);
        $entityTypeFieldsQuery->addEntityMapping('val', FieldValueNode::class, Query::HYDRATE_COLLECTION);
        $entityTypeFieldsQuery->setParameter('oId', $objectNode->getId());
        $entityTypeFieldsQuery->setParameter('slugs', $slugs);

        return $entityTypeFieldsQuery->execute();
    }

    /**
     * Seeks specific object`s parent type by slug
     *
     * @param ObjectNode $objectNode
     * @param string $slug
     *
     * @return array|mixed
     */
    public function getParentTypeBySlug(ObjectNode $objectNode, $slug)
    {
        $query = $this->entityManager->createQuery(
            'MATCH (pet:EntityType)<-[:is_child_of]-(et:EntityType)<-[:has_type]-(o:Object)
            WHERE id(o) = {oId} AND pet.slug = {slug}
            RETURN pet LIMIT 1'
        );
        $query->addEntityMapping('pet', TypeFieldNode::class);
        $query->setParameter('oId', $objectNode->getId());
        $query->setParameter('slug', $slug);
        $result = $query->getOneOrNullResult();

        return ($result) ? $result[0] : $result;
    }

    /**
     * Seeks specific object`s parent type by slug
     *
     * @param ObjectNode $objectNode
     * @param string $slug
     *
     * @return array|mixed
     */
    public function getParentObjectByTypeSlug(ObjectNode $objectNode, $slug)
    {
        $query = $this->entityManager->createQuery(
            'MATCH (pobj:Object)-[:has_type]-(pet:EntityType)<-[:is_child_of]-(et:EntityType)<-[:has_type]-(o:Object)
            WHERE id(o) = {oId} AND pet.slug = {slug} AND (pobj)-[:is_child_of]-(o)
            RETURN pobj LIMIT 1'
        );
        $query->addEntityMapping('pobj', ObjectNode::class);
        $query->setParameter('oId', $objectNode->getId());
        $query->setParameter('slug', $slug);
        $result = $query->getOneOrNullResult();

        return ($result) ? $result[0] : $result;
    }

    public function getMediaDropdownChoices(int $entityType, int $fieldTypeId = null)
    {
        $fieldTypeIdFilter = (is_null($fieldTypeId)) ? '' : ' AND id(etf) = ' . $fieldTypeId;
        $query = $this->entityManager->createQuery(
            'MATCH (etf:EntityTypeField {fieldType:\'file\'})--(et:EntityType)<-[:has_type]-(o:Object)  
                WHERE id(et) = {entityTypeId}' . $fieldTypeIdFilter . '
            OPTIONAL MATCH (o)<--(fv:FieldValue)-->(etf:EntityTypeField {isMainField:true})-->(et)
            RETURN id(o) as id, o.name as name, collect(fv.data) as values, et.name as type'
        );
        $query->setParameter('entityTypeId', $entityType);
        $result = [];
        foreach ($query->getResult() as $row) {
            // if object has name - return it, otherwise try to join main fields
            $name = (empty($row['name']) ? join(', ', $row['values']) : $row['name']);
            // if name is empty at this point -  just return object ID + entityType name
            $name = (empty($name) ? $row['id'] . '-' . $row['type'] : $name);

            $result[] = [
                'name' => $name,
                'value' => $row['id'],
                'text' => $name,
            ];
        }

        return $result;
    }

    /**
     * Seeks of all object`s fields and values by field slugs
     *
     * @param ObjectNode $objectNode
     * @param int $tfId
     *
     * @return array|mixed
     */
    public function getValByTypeFieldId(ObjectNode $objectNode, int $tfId)
    {
        $entityTypeFieldsQuery = $this->entityManager->createQuery(
            'MATCH (fv)-->(etf:EntityTypeField)-[:has_field]->(et:EntityType)<-[:has_type]-(o:Object)<--(fv:FieldValue)
            WHERE id(o) = {oId} AND id(etf) = {tfId} RETURN fv'
        );
        $entityTypeFieldsQuery->addEntityMapping('fv', FieldValueNode::class);
        $entityTypeFieldsQuery->setParameter('oId', $objectNode->getId());
        $entityTypeFieldsQuery->setParameter('tfId', $tfId);
        $result = $entityTypeFieldsQuery->getOneOrNullResult();

        return is_null($result) ? $result : $result[0];
    }

    public function topSearchByName(string $name)
    {
        $name = str_replace(' ', '|', $name);
        $query = $this->entityManager->createQuery(
            "MATCH (o:Object)--(et:EntityType) WHERE et.isDataType = false AND o.name =~ {name} 
            WITH o, et ORDER BY o.name
            RETURN collect(o) as objects, et ORDER BY et.name LIMIT 10");
        $query->addEntityMapping('objects', ObjectNode::class, Query::HYDRATE_COLLECTION);
        $query->addEntityMapping('et', EntityTypeNode::class);
        $query->setParameter('name', '(?i).*(' . $name . ').*');

        return $query->getResult();
    }

    public function topSearchWithImgByName(string $name)
    {
        $name = str_replace(' ', '|', $name);
        $query = $this->entityManager->createQuery(
            "MATCH (o:Object)--(et:EntityType) where et.isDataType = false AND o.name =~ {name}
            OPTIONAL MATCH (o)<--(fv:FieldValue)-->(etf:EntityTypeField)-->(et) WHERE etf.slug = et.mainPictureField
            WITH o, et, fv.webPath AS fv ORDER BY o.name
            RETURN collect({obj:o, img:fv}) as objects, et ORDER BY et.name LIMIT 10");
        $query->addEntityMapping('obj', ObjectNode::class);
        $query->addEntityMapping('objects', ObjectNode::class, Query::HYDRATE_MAP_COLLECTION);
        $query->addEntityMapping('et', EntityTypeNode::class);
        $query->setParameter('name', '(?i).*(' . $name . ').*');

        return $query->getResult();
    }

    public function updateUserToObjectRelation(UserNode $userNode, ObjectNode $objectNode)
    {
        $query = $this->entityManager->createQuery(
            "MATCH (newU:User),(o:Object) WHERE id(o) = {oId} AND newU.email = {userEmail}
             OPTIONAL MATCH (o)-[oldLink:created_by]->(oldU:User)
             DETACH DELETE oldLink
             MERGE (newU)<-[:created_by]-(o)"
        );
        $query->setParameter('userEmail', $userNode->getEmailCanonical());
        $query->setParameter('oId', $objectNode->getId());
        $query->execute();
    }

    protected function getCreateRelationsQuery(bool $isChildsLink): string
    {
        $queryDirection = $this->getQueryDirections($isChildsLink);
        $leftPart = $queryDirection['leftPart'];
        $rightPart = $queryDirection['rightPart'];

        return "MATCH (o:Object),(add:Object)
                    WHERE id(o)={id} AND id(add) in {id_add}
                    CREATE UNIQUE (o)$leftPart-[:is_child_of]-$rightPart(add)
                    return o";
    }

    protected function getDeleteRelationsQuery(bool $isChildsLink): string
    {
        $queryDirection = $this->getQueryDirections($isChildsLink);
        $leftPart = $queryDirection['leftPart'];
        $rightPart = $queryDirection['rightPart'];

        return "MATCH (o:Object)$leftPart-[r:is_child_of]-$rightPart(rem:Object)
                    WHERE id(o)={id} AND id(rem) in {id_rem}
                    DELETE r
                    return o";
    }
}