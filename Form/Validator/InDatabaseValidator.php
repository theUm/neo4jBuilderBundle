<?php

namespace Nodeart\BuilderBundle\Form\Validator;

use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @Annotation
 */
class InDatabaseValidator extends ConstraintValidator
{
    private $objectNodeRepository;
    private $nm;

    public function __construct(EntityManager $nm)
    {
        $this->nm = $nm;
        $this->objectNodeRepository = $nm->getRepository(ObjectNode::class);
        $nm->getResultMappingMetadata(ObjectNode::class)->getFields();

    }

    public function validate($entity, Constraint $constraint)
    {
        $fields = explode(',', $constraint->fields);
        $class = $this->nm->getClassMetadata(get_class($entity));
        $hasError = false;
        $errorField = '';
        foreach ($fields as $fieldName) {
            $fieldValue = $class->getPropertyMetadata($fieldName)->getValue($entity);
            $res = $this->objectNodeRepository->findBy([$fieldName => $fieldValue]);
            //if there is no record in DB with such value or if it is same entity
            $hasError = !(count($res) === 0 || ((count($res) === 1) && ($res[0] === $entity)));
            if ($hasError) {
                $errorField = $fieldName;
                break;
            }
        }

        if (!$hasError)
            return;

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{fieldValue}}', $fieldValue)
            ->atPath($errorField)
            ->addViolation();
    }
}