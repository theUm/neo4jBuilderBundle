<?php

namespace Nodeart\BuilderBundle\Twig\Utils;

use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;

class TypeFieldValuePairTransformer {

    public static function transformValueToView( ?FieldValueNode $fieldValueNode, TypeFieldNode $typeFieldNode, $callback = null ) {
        $res           = '';
        $fieldTypeName = $typeFieldNode->getFieldType();
        if (!is_null($fieldValueNode)) {
            //pass file node as is
            if ($fieldTypeName == 'file') {
                $res = $fieldValueNode;
            } elseif (!empty($fieldValueNode->getData())) {
                switch ($fieldTypeName) {
                    case 'checkbox': {
                        $res = $fieldValueNode->getData() ? 'true' : 'false';
                        break;
                    }
                    case 'text':
                    case 'simple_text':
                    case 'predefSelect2':
                    case 'textArea':
                    case 'email':
                    case 'integer':
                    case 'number':
                    case 'money':
                    case 'url':
                    case 'wysiwyg': {
                        $res = $fieldValueNode->getData();
                        break;
                    }
                    case 'choice': {
                        //@todo implement choice field type
                        break;
                    }
                    case 'date': {
                        $date = new \DateTime($fieldValueNode->getData());
                        $res = $date->format('d.m.Y');
                        break;
                    }
                    case 'time': {
                        $date = new \DateTime($fieldValueNode->getData());
                        $res = $date->format('H:i:s');
                        break;
                    }
                    case 'datetime': {
                        $date = new \DateTime($fieldValueNode->getData());
                        $res = $date->format('d.m.Y H:i:s');
                        break;
                    }
                    case 'radio': {
                        //@todo implement radio field type
                        break;
                    }
                    case 'labeled_number': {
                        $res = $fieldValueNode->getData() . ' ' . $fieldValueNode->getDataLabel();
                        break;
                    }
                }
            }
        }
        return $res;
    }
}