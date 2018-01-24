<?php

namespace Nodeart\BuilderBundle\Helpers;

use Nodeart\BuilderBundle\Entity\TypeFieldNode;

class FieldAndValue
{
    /**
     * @var TypeFieldNode
     */
    private $type;
    /**
     * @var mixed|array
     */
    private $val;

    public function __construct(TypeFieldNode $type, $value)
    {
        $this->type = $type;
        if (is_null($value)) {
            $this->val = $type->isCollection() ? [] : null;
        } elseif ($type->isCollection()) {
            $this->val = is_array($value) ? $value : [$value];
        } else {
            $this->val = $value;
        }
    }

    /**
     * @return mixed|array
     */
    public function getVal()
    {
        return $this->val;
    }

    /**
     * @param mixed $value
     */
    public function addVal($value)
    {
        if ($this->getType()->isCollection()) {
            if (is_array($value)) {
                $this->val = array_merge($this->val, $value);
            } else {
                array_push($this->val, $value);
            }
        } else {
            $this->val = $value;
        }
    }

    /**
     * @return TypeFieldNode
     */
    public function getType(): TypeFieldNode
    {
        return $this->type;
    }

    /**
     * @param TypeFieldNode $type
     */
    public function setType(TypeFieldNode $type)
    {
        $this->type = $type;
    }

}