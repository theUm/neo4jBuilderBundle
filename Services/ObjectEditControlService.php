<?php

namespace Nodeart\BuilderBundle\Services;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\Repositories\ObjectNodeRepository;
use Nodeart\BuilderBundle\Form\ObjectNodeType;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;

/**
 *
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 * @property null|object object
 */
class ObjectEditControlService
{
    const WITH_PARENT_OBJECTS_FIELDS = true;
    const WITHOUT_PARENT_OBJECTS_FIELDS = false;

    private $container;
    /** @var EntityManager $nm */
    private $nm;
    private $formFieldsService;
    /** @var ObjectNodeRepository $oRepository */
    private $oRepository;

    private $dynamicFieldsIds = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->nm = $container->get('neo.app.manager');
        $this->oRepository = $this->nm->getRepository(ObjectNode::class);
        $this->formFieldsService = $this->container->get('object.form.fields');
    }

    public function fetchObjectById($id)
    {
        return $this->oRepository->findOneById($id);
    }

    /**
     * @param ObjectNode $objectNode
     *
     * @param bool $withParentFields
     * @param bool $isAjax
     * @return \Symfony\Component\Form\FormBuilderInterface
     * @throws \Exception
     */
    public function prepareForm(ObjectNode $objectNode, $withParentFields = self::WITHOUT_PARENT_OBJECTS_FIELDS, $isAjax = false)
    {
        $formBuilder = $this->container->get('form.factory')
            ->createNamedBuilder('obj_fields', ObjectNodeType::class, $objectNode, [
                'attr' => [
                    'id' => 'obj_fields',
                    'class' => 'ui form'
                ]
            ]);

        $this->formFieldsService->setObject($objectNode);
        // hide relations fields for data objects
        if ($withParentFields) {
            $this->formFieldsService->addParentTypesFields($formBuilder, $isAjax);
        }
        $formBuilder->get('createdBy')->setData($objectNode->getCreatedBy());
        $this->formFieldsService->hideFieldsForDataType($formBuilder);

        $this->dynamicFieldsIds = $this->formFieldsService->addFormFields($formBuilder);

        return $formBuilder;
    }

    public function saveForm(Form $form, $silent = true)
    {
        //update name, slug, desc
        $this->oRepository->updateObjectNodeData($this->getObject()->getId(), $this->getObject()->toArray());

        //this is normal way to save relations, and it not works for now
        //$this->formFieldsService->setParentsToObject($form);
        //$this->nm->getNM()->persist($this->getObject());
        //$this->nm->getNM()->flush();


        //update links to parent objects
        $pickedParentsIds = $this->formFieldsService->getPickedParentIds($form);
        $this->oRepository->updateParenthesisRelationsByIds(
            $this->getObject()->getId(),
            $pickedParentsIds,
            $this->formFieldsService->getCurrentParentObjectsIds(),
            ObjectNodeRepository::LINK_TO_PARENTS
        );

        $this->formFieldsService->updateAuthor($form);
        if (!$silent) {
            $this->container->get('session')->getFlashBag()->add('success', 'Объект успешно изменён');
        }
        // update FieldsValues in separate queries
        $this->formFieldsService->handleDynamicFields($form, $this->dynamicFieldsIds);
        if (!$silent) {
            $this->container->get('session')->getFlashBag()->add('success', 'Поля тоже успешно изменёны');
        }
    }

    private function getObject()
    {
        return $this->formFieldsService->getObject();

    }

    public function getFieldValuesByGroups()
    {
        return $this->formFieldsService->getFieldValuesByGroups();
    }

    public function getRelatedChildsByTypes()
    {
        $childByTypes = [];
        foreach ($this->getObject()->getChildObjects() as $childObject) {
            $childByTypes[$childObject->getEntityType()->getId()][] = $childObject;
        }

        return $childByTypes;
    }
}