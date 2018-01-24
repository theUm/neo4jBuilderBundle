<?php

namespace Nodeart\BuilderBundle\Helpers;

use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Symfony\Component\DependencyInjection\Container;


/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class TemplateControllerHelper
{
    const NODE_CLASSES = [
        'EntityType' => EntityTypeNode::class,
        'Object' => ObjectNode::class,
        'FieldValue' => FieldValueNode::class,
        'EntityTypeField' => TypeFieldNode::class,
    ];
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getNodeClassByName(string $name)
    {
        return (self::NODE_CLASSES[$name]) ?? false;
    }

    public function getNameByNodeClass(string $nodeClass)
    {
        $arr = array_flip(self::NODE_CLASSES);

        return ($arr[$nodeClass]) ?? false;
    }

}