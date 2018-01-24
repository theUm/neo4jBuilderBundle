<?php
/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 17:07
 */

namespace Nodeart\BuilderBundle\Services;

use Doctrine\Common\Collections\ArrayCollection;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\EntityTypeNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\FieldValueNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Entity\Repositories\TypeFieldNodeRepository;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\LabeledNumberType;
use Nodeart\BuilderBundle\Form\Type\LabeledTextType;
use Nodeart\BuilderBundle\Form\Type\NamedFileType;
use Nodeart\BuilderBundle\Form\Type\NodeCheckboxType;
use Nodeart\BuilderBundle\Form\Type\PredefinedAjaxCheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

//@todo MAJOR REFACTORING REQUIRED. ARRAY HELL & INEFFECTIVE ORM USAGE DETECTED
class ObjectFormFieldsService
{
    private $fnb;
    /** @var EntityManager $nm */
    private $nm;
    private $object;
    /**
     * @var $entityType EntityTypeNode
     */
    private $entityType = null;
    private $parentObjId = null;
    /**
     * @var $parentObjectNode ObjectNode
     */
    private $parentObjectNode = null;
    private $typesByIds = null;
    private $existedValues = null;
    private $fieldValuesArray = null;
    private $currentParentObjectsIds = [];
    private $parentEntityTypes = null;

    /** @var ObjectNodeRepository $oRepository */
    private $oRepository;
    /** @var EntityTypeNodeRepository $etRepository */
    private $etRepository;
    /** @var TypeFieldNodeRepository $tfRepository */
    private $tfRepository;
    /** @var FieldValueNodeRepository $valueRepository */
    private $valueRepository;

    public function __construct(FormNodeBridge $fnb, EntityManager $nm)
    {
        $this->nm = $nm;
        $this->fnb = $fnb;
        $this->oRepository = $nm->getRepository(ObjectNode::class);
        $this->tfRepository = $nm->getRepository(TypeFieldNode::class);
        $this->valueRepository = $nm->getRepository(FieldValueNode::class);
        $this->etRepository = $nm->getRepository(EntityTypeNode::class);
    }

    public function addFormFields(FormBuilderInterface $form)
    {
        $addedFormFieldIds = [];

        foreach ($this->getFieldValuesStruct() as $typeId => $typeAndValue) {
            /** @var TypeFieldNode $fieldTypeNode */
            $fieldTypeNode = $typeAndValue['type'];
            /** @var FieldValueNode[] $fieldValueNodes */
            $fieldValueNodes = $typeAndValue['val'];
            /** @var array $fieldValuesData contains actual fieldValue data */
            $fieldValuesData = [];
            /**
             * @todo: use Symfony PropertyPathMapper to map data
             * @var FieldValueNode $fieldValueNode
             */
            foreach ($fieldValueNodes as $fieldValueNode) {
                $fieldValuesData[] = $this->fnb->transformNodeValueToForm($fieldValueNode, $fieldTypeNode);
            }

            $formType = $this->fnb->getFormClassByName($fieldTypeNode->getFieldType());

            if ($fieldTypeNode->isCollection() && !in_array($formType, [
                    PredefinedAjaxCheckboxType::class,
                    NamedFileType::class,
                    LabeledNumberType::class,
                    LabeledTextType::class
                ])) {
                $formType = AjaxCheckboxType::class;
            }

            $dbOptions = (array)json_decode($fieldTypeNode->getOptions());
            $fieldOptions = $this->fnb->getDefaultFormConfig($formType, $fieldTypeNode, $fieldValuesData);
            $fieldOptions = array_merge(
                $fieldOptions,
                $dbOptions
            );
            $fieldOptions['required'] = $fieldTypeNode->isRequired();

            //NamedFileType needs object id to link fieldValue to that object
            if ($formType == NamedFileType::class) {
                $fieldOptions['object_id'] = ($this->getObject()) ? $this->getObject()->getId() : false;
            }

            if (in_array($formType, [LabeledNumberType::class, LabeledTextType::class]) && isset($fieldOptions['multiple']) && $fieldOptions['multiple']) {
                $baseFormType = $formType;
                $formType = CollectionType::class;
                unset($fieldOptions['multiple']);
                unset($fieldOptions['is_multiple']);
                $fieldOptions['entry_type'] = $baseFormType;
                $fieldOptions['entry_options'] = ['label' => false, 'is_multiple' => true, 'multiple' => true];
                $fieldOptions['allow_add'] = true;
                $fieldOptions['allow_delete'] = true;
            }

            $addedFormFieldIds[] = $typeId;
            $form->add(
                $typeId,
                $formType,
                $fieldOptions
            );
            $form->get($typeId)->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($typeId) {
                $data = $event->getData();
                $form = $event->getForm();

                if ($form->isRequired() && empty($data)) {
                    $form->addError(new FormError('This field is required and cannot be empty'));
                }
            });
        }

        return $addedFormFieldIds;
    }

    /**
     * Returns field types and values array with structure acceptable by this form field service
     * @return array
     */
    public function getFieldValuesStruct()
    {
        $fieldValsByTypes = [];
        // if object present (edit page) - fetch structure form db
        if ($this->getObject()->getId() != null) {
            $fieldTypes = $this->oRepository->getFields($this->getObject());
            foreach ($fieldTypes as $pair) {
                /** @var TypeFieldNode $fieldType */
                $fieldType = $pair['type'];
                $fieldValsByTypes[$fieldType->getId()] = $pair;
            }
            // if object is absent (create object page) - fetch field types from other source
        } else {
            /** @var TypeFieldNode $fieldType */
            foreach ($this->getFieldTypesByEntityType() as $fieldType) {
                $fieldValsByTypes[$fieldType->getId()] = ['type' => $fieldType, 'val' => []];
            }
        }

        return $fieldValsByTypes;
    }

    /**
     * @return ObjectNode
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param ObjectNode $object
     */
    public function setObject(ObjectNode $object)
    {
        $this->object = $object;
        /** @var ObjectNode $parentObject */
        foreach ($object->getParentObjects() as $parentObject) {
            $this->currentParentObjectsIds[] = $parentObject->getId();
        }
        // clear types and values if object changed
        $this->typesByIds = $this->existedValues = null;
    }

    private function getFieldTypesByEntityType()
    {
        if (is_null($this->typesByIds)) {
            $this->initObjectFieldTypes();
        }

        return $this->typesByIds;
    }

    private function initObjectFieldTypes()
    {
        $this->typesByIds = [];
        /** @var TypeFieldNode $fieldType */
        foreach ($this->tfRepository->findEntityTypeFieldsByType($this->getEntityType()->getSlug()) as $fieldType) {
            $this->typesByIds[$fieldType->getId()] = $fieldType;
        }
    }

    /**
     * @return EntityTypeNode|null
     */
    public function getEntityType()
    {
        //PHP7! PHP7 IN UR FACE!
        return $this->entityType ?? $this->getObject()->getEntityType();
    }

    /**
     * @param EntityTypeNode $entityType
     */
    public function setEntityType(EntityTypeNode $entityType)
    {
        $this->entityType = $entityType;
    }

    /**
     * @return EntityManager
     */
    public function getNM(): EntityManager
    {
        return $this->nm;
    }

    /**
     * @param int $id
     */
    public function setParentObjId($id)
    {
        $this->parentObjId = $id;
    }

    /**
     * @param ObjectNode|null $objectNode
     */
    public function setParentObj($objectNode)
    {
        $this->parentObjectNode = $objectNode;
    }

    public function addParentTypesFields(FormBuilderInterface $form, $isAjax = false)
    {
        $currentParentsByTypes = [];

        $entityType = $this->getEntityType();
        $objectParentTypes = $entityType->getParentTypes();
        //init parent entity types collection
        foreach ($objectParentTypes as $parentType) {
            $currentParentsByTypes[$parentType->getId()] = new ArrayCollection();
        }


//        VarDumper::dump($entityType->getRequiredParents());

        //fill types with objects
        /** @var ObjectNode $parentObject */
        foreach ($this->getObject()->getParentObjects() as $parentObject) {
            $currentParentsByTypes[$parentObject->getEntityType()->getId()]->add($parentObject);
        }
        if (!is_null($this->parentObjectNode) && isset($currentParentsByTypes[$this->parentObjectNode->getEntityType()->getId()])) {
            $currentParentsByTypes[$this->parentObjectNode->getEntityType()->getId()]->add($this->parentObjectNode);
        }

        /** @var EntityTypeNode $parentType */
        foreach ($objectParentTypes as $parentType) {
            $isHidden = $this->getEntityType()->isDataType() && $isAjax;
            $isRequired = !$isHidden && in_array($parentType->getId(), $entityType->getRequiredParents() ?? []);
            $constraints = ($isRequired) ? [new NotBlank()] : [];
            $form->add($parentType->getSlug(), NodeCheckboxType::class, [
                'label' => 'Принадлежит объектам типа',
                'label_attr' => ['tooltip' => $parentType->getName()],
                'data' => $currentParentsByTypes[$parentType->getId()],
                'is_multiple' => true,
                'local_search_data' => $parentType->getObjects(),
                'is_hidden' => $isHidden,
                'required' => $isRequired,
                'constraints' => $constraints,
                'error_bubbling' => false
            ]);
        }
    }


    public function addSingleParentTypeField(FormBuilderInterface $form)
    {
        $form->add($this->parentObjectNode->getEntityType()->getSlug(), NodeCheckboxType::class, [
            'label' => 'Принадлежит объектам типа',
            'label_attr' => ['tooltip' => $this->parentObjectNode->getEntityType()->getName()],
            'data' => [$this->parentObjectNode],
            'is_multiple' => true,
            'local_search_data' => [$this->parentObjectNode],
            'is_hidden' => true,
            'required' => false
        ]);
    }

    public function setParentsToObject(Form $form)
    {
        //reset parent objects
        $parentObjects = [];
        $this->getObject()->getParentObjects()->clear();
        foreach ($this->getObject()->getEntityType()->getParentTypes() as $parentType) {
            if ($form->has($parentType->getSlug())) {
                /** @var ArrayCollection|null $fieldData */
                $fieldData = $form->get($parentType->getSlug())->getData();
                if ($fieldData) {
                    $parentObjects = array_merge($parentObjects, $fieldData->toArray());
                }
            }
        }
        foreach ($parentObjects as $parent) {
            $this->getObject()->getParentObjects()->add($parent);
        }
    }

    public function getPickedParentIds(Form $form)
    {
        $pickedObjectIds = [];
        /** @var EntityTypeNode $parentType */
        foreach ($this->getParentEntityTypes()->getIterator() as $parentType) {
            if ($form->has($parentType->getSlug()) && !empty($form->get($parentType->getSlug())->getData())) {
                $data = $form->get($parentType->getSlug())->getData();
                $currIds = [];
                /** @var ObjectNode $object */
                foreach ($data as $object) {
                    $currIds[] = $object->getId();
                }
                $pickedObjectIds = array_merge($pickedObjectIds, $currIds);
            }
        }

        return $pickedObjectIds;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|null
     */
    private function getParentEntityTypes()
    {
        if (is_null($this->parentEntityTypes)) {
            $this->parentEntityTypes = $this->getEntityType()->getParentTypes();
        }

        return $this->parentEntityTypes;
    }

    public function getCurrentParentObjectsIds()
    {
        return $this->currentParentObjectsIds;
    }

    /**
     * @return array
     */
    public function getFieldValuesByGroups()
    {
        $fieldValsByGroups = [];
        /** @var TypeFieldNode $typeFieldNode */
        foreach ($this->getFieldTypesByEntityType() as $fieldTypeId => $typeFieldNode) {
            $tabGroup = empty($typeFieldNode->getTabGroup()) ? 'default' : $typeFieldNode->getTabGroup();
            $valsByTypeId = $this->getObjectFieldValuesByTypes();
            if (isset($valsByTypeId[$fieldTypeId])) {
                $value = $valsByTypeId[$fieldTypeId];
            } else {
                $value = [new FieldValueNode()];
            }
            $fieldValsByGroups[$tabGroup][] = [
                'type' => $typeFieldNode,
                'val' => $value // value is always array
            ];
        }
        if (isset($fieldValsByGroups['default'])) {
            $defaultGroup = $fieldValsByGroups['default'];
            unset($fieldValsByGroups['default']);
            $fieldValsByGroups = ['default' => $defaultGroup] + $fieldValsByGroups;
        }

        return $fieldValsByGroups;
    }

    private function getObjectFieldValuesByTypes()
    {
        if (is_null($this->existedValues)) {
            $this->initObjectFieldValuesByTypes();
        }

        return $this->existedValues;
    }

    private function initObjectFieldValuesByTypes()
    {
        $this->existedValues = [];
        /** @var FieldValueNode $f */
        foreach ($this->getObject()->getFieldVals()->getValues() as $f) {
            $this->existedValues[$f->getTypeField()->getId()][] = $f;
        }
    }

    public function handleDynamicFields(Form $form, $dynamicFieldsIds, ObjectNode $object = null)
    {
        /** @var Form $formFieldValue */
        foreach ($form->getIterator() as $fieldTypeId => $formFieldValue) {
            if (!($formFieldValue instanceof Form) || !(in_array($fieldTypeId, $dynamicFieldsIds))) {
                continue;
            }

            $fieldValueNodes = $this->getFnb()->transformFormToNodeValue($formFieldValue);
            $valuesByActions = $this->getValuesByActions($formFieldValue, $fieldValueNodes);

            $fieldTypeId = intval($fieldTypeId);
            $object = $object ?? $this->getObject();

            $this->valueRepository->createFieldValues($valuesByActions['create'], $fieldTypeId, $object);
            $this->valueRepository->deleteFieldValues($valuesByActions['delete'], $fieldTypeId, $object);
            $this->valueRepository->updateFieldValuesOrder($valuesByActions['updateOrder'], $fieldTypeId, $object);
        }
    }

    /**
     * @return FormNodeBridge
     */
    public function getFnb(): FormNodeBridge
    {
        return $this->fnb;
    }

    /**
     * @param Form $formFieldValue
     * @param $values
     *
     * @return array ['delete','create']
     * @internal param int $typeId
     */
    public function getValuesByActions(Form $formFieldValue, $values)
    {
        $actionsArray = [
            'delete' => [],
            'create' => [],
            'updateOrder' => [],
        ];
        $typeId = $formFieldValue->getName();
        $previousValues = $this->getFieldValuesAsArray();
        if (!is_array($values)) {
            $values = [$values];
        }
        if (isset($previousValues[$typeId])) {
            //if its file we need to keep file if we did not uploaded anything
            if ($this->isFileType($formFieldValue)) {
                $actionsArray['create'] = array_diff($values, $previousValues[$typeId]);
                if (!empty($actionsArray['create'])) {
                    $actionsArray['delete'] = array_diff($previousValues[$typeId], $values);
                }
            } else { //just replace old vals with new if needed
                $actionsArray['delete'] = array_udiff($previousValues[$typeId], $values,
                    function (FieldValueNode $obj_a, FieldValueNode $obj_b) {
                        return $obj_a->getDataLabel() !== $obj_b->getDataLabel() || $obj_a->getData() !== $obj_b->getData();
                    }
                );
                $actionsArray['create'] = array_udiff($values, $previousValues[$typeId],
                    function (FieldValueNode $obj_a, FieldValueNode $obj_b) {
                        return $obj_a->getDataLabel() !== $obj_b->getDataLabel() || $obj_a->getData() !== $obj_b->getData();
                    }
                );
                $actionsArray['updateOrder'] = $values;
            }
        } else {
            $actionsArray['create'] = $values;
        }

        return $actionsArray;
    }

    private function getFieldValuesAsArray($forceCreate = false)
    {
        if ($this->fieldValuesArray == null || $forceCreate) {
            $this->fieldValuesArray = [];
            foreach ($this->getFieldValuesStruct() as $typeId => $pair) {
                if (!isset($this->fieldValuesArray[$typeId])) {
                    $this->fieldValuesArray[$typeId] = [];
                }
                /** @var FieldValueNode $fieldValue */
                foreach ($pair['val'] as $fieldValue) {
                    if (!empty($fieldValue->getId())) {
                        $this->fieldValuesArray[$typeId][] = $fieldValue;
                    }
                }
            }
        }

        return $this->fieldValuesArray;
    }

    private function isFileType(Form $formFieldValue)
    {
        return in_array(get_class($formFieldValue->getConfig()->getType()->getInnerType()), [
            FileType::class,
            NamedFileType::class
        ]);
    }

    public function hideFieldsForDataType(FormBuilderInterface $formBuilder)
    {
        if ($this->getEntityType()->isDataType()) {
            $formBuilder->remove('name');
            $formBuilder->remove('slug');
            $formBuilder->remove('description');
            $formBuilder->remove('isCommentable');
            $formBuilder->remove('status');
        }
    }

}