<?php

namespace Nodeart\BuilderBundle\Form\Validator\Constraint;

use Nodeart\BuilderBundle\Form\Validator\InDatabaseValidator;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class InDatabase extends Constraint
{
    public $message;
    public $fields;

    public function __construct($options = null)
    {
        if (empty($options['fields'])) {
            throw new LogicException(sprintf('Parameter "%s" of %s class is missing from its constructor or annotation', 'fields', self::class));
        }
        $this->message = 'Object with "{{fieldValue}}" is already exists.';
        parent::__construct($options);
    }

    public function validatedBy()
    {
        return InDatabaseValidator::class;
    }


    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}