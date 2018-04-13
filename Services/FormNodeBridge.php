<?php
/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 17:07
 */

namespace Nodeart\BuilderBundle\Services;


use Nodeart\BuilderBundle\Entity\EntityTypeNode;
use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Entity\TypeFieldNode;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\DoubleLabeledNumberType;
use Nodeart\BuilderBundle\Form\Type\DoubleLabeledTextType;
use Nodeart\BuilderBundle\Form\Type\LabeledNumberType;
use Nodeart\BuilderBundle\Form\Type\LabeledTextType;
use Nodeart\BuilderBundle\Form\Type\NamedFileType;
use Nodeart\BuilderBundle\Form\Type\PredefinedAjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\WysiwygType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Form;

class FormNodeBridge
{
    const REL_IS_VALUE_OF = 'is_value_of';
    const REL_IS_VARIANT_OF = 'is_variant_of';
    const TYPES = [
        'checkbox' => ['class' => CheckboxType::class],
        'text' => ['class' => AjaxCheckboxType::class],
        'simple_text' => ['class' => TextType::class],
        'predefSelect2' => ['class' => PredefinedAjaxCheckboxType::class],
        'textArea' => ['class' => TextareaType::class],
        'email' => ['class' => EmailType::class],
        'integer' => ['class' => IntegerType::class],
        'number' => ['class' => NumberType::class],
        'money' => ['class' => MoneyType::class],
        'url' => ['class' => UrlType::class],
        'choice' => ['class' => ChoiceType::class, 'relName' => self::REL_IS_VARIANT_OF],
        'date' => ['class' => DateType::class],
        'time' => ['class' => TimeType::class],
        'datetime' => ['class' => DateTimeType::class],
        'radio' => ['class' => RadioType::class, 'relName' => self::REL_IS_VARIANT_OF],
        'file' => ['class' => NamedFileType::class],
        'wysiwyg' => ['class' => WysiwygType::class],
        'labeled_number' => ['class' => LabeledNumberType::class],
        'labeled_text' => ['class' => LabeledTextType::class],
        'double_labeled_number' => ['class' => DoubleLabeledNumberType::class],
        'double_labeled_text' => ['class' => DoubleLabeledTextType::class],
    ];

    protected $uploadsDir;
    protected $webUploadsDir;

    protected $deletedPredefinedValues = [];

    public function __construct($uploadsDir, $webUploadsDir)
    {
        foreach (self::TYPES as &$type) {
            if (!isset($type['relName'])) {
                $type['relName'] = self::REL_IS_VALUE_OF;
            }
        }
        $this->uploadsDir = $uploadsDir;
        $this->webUploadsDir = $webUploadsDir;
    }

    public static function flattenObjectsByTypes(array $objectsByTypes): array
    {
        $result = [];
        foreach ($objectsByTypes as $type => $objects) {
            foreach ($objects as $object) {
                $result[$object] = '[' . $type . '] ' . $object;
            }
        }

        return $result;
    }

    /**
     * Returns FieldTypeNodes to delete from EntityType
     *
     * @param EntityTypeNode $entityTypeNode
     * @param array $formData
     *
     * @return array
     */
    public static function getDeletedFieldIds(EntityTypeNode $entityTypeNode, array $formData)
    {
        $formDataIds = self::getFormDataIds($formData);
        $deletedIds = [];
        foreach ($entityTypeNode->getEntityTypeFields()->getIterator() as $entityTypeField) {
            if (!(in_array($entityTypeField->getId(), $formDataIds))) {
                $deletedIds[] = $entityTypeField->getId();
            }
        }

        return $deletedIds;
    }

    private static function getFormDataIds(array $formData)
    {
        $formDataIds = [];
        foreach ($formData as $formField) {
            $formDataIds[] = $formField['id'];
        }

        return array_filter($formDataIds);
    }

    public function isFieldHasRel($name, $relName)
    {
        return ($this->getRelByName($name) == $relName) ? true : false;
    }

    public function getRelByName($name)
    {
        return $this->getTypeSmthnByName($name, 'relName');
    }

    public function getTypeSmthnByName($name, $smthn)
    {
        $types = self::TYPES;

        return (isset($types[$name][$smthn])) ? $types[$name][$smthn] : [];
    }

    public function getDeletedPredefinedValues()
    {
        return $this->deletedPredefinedValues;
    }

    public function getChangedTypeNodesData(EntityTypeNode $entityTypeNode, array $formData)
    {
        $reformattedFormData = [];
        foreach ($formData as $field) {
            if (!empty($field['id'])) {
                $reformattedFormData[$field['id']] = $field;
            }
        }

        $editedNodesData = [];
        /** @var TypeFieldNode $field */
        foreach ($entityTypeNode->getEntityTypeFields()->getIterator() as $field) {
            if (
            in_array($field->getId(), array_keys($reformattedFormData))
            ) {
                $fieldAsArray = $field->toArray();
                $positiveDifference = $this->array_diff_assoc_recursive($reformattedFormData[$field->getId()], $fieldAsArray);
                $negativeDifference = $this->array_diff_assoc_recursive($fieldAsArray, $reformattedFormData[$field->getId()]);

                if (!empty($positiveDifference) || !empty($negativeDifference)) {
                    $editedNodesData[$field->getId()] = $reformattedFormData[$field->getId()];
                    if (isset($negativeDifference['predefinedFields'])) {
                        $this->deletedPredefinedValues[$field->getId()] = $negativeDifference['predefinedFields'];
                    }
                }
            }
        }

        return $editedNodesData;
    }

    private function array_diff_assoc_recursive($array1, $array2)
    {
        $difference = array();
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } else if (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return $difference;
    }

    /**
     * @todo: use symfony transformer instead
     *
     * @param FieldValueNode $value
     * @param TypeFieldNode $typeNode
     *
     * @return FieldValueNode|\DateTime|null|string
     */
    public function transformNodeValueToForm(FieldValueNode $value, TypeFieldNode $typeNode)
    {
        switch ($this->getFormClassByName($typeNode->getFieldType())) {
            case DateTimeType::class :
            case TimeType::class :
            case DateType::class :
                {
                    if ($value->getData()) {
                        // @todo remove 'd.m.Y H:i:s' condition. From now only unix time will be stored
                        $value = \DateTime::createFromFormat(is_numeric($value->getData()) ? 'U' : 'd.m.Y H:i:s', $value->getData());
                    } else {
                        $value = null;
                    }
                    break;
                }
            /*case FileType::class : {
                $value = null;
                break;
            }*/
            case NamedFileType::class :
                {
                    break;
                }
            case LabeledTextType::class :
            case LabeledNumberType::class :
                {
                    $value = [
                        'value' => $value->getData(),
                        'text' => $value->getDataLabel()
                    ];
                    break;
                }
            case DoubleLabeledTextType::class :
            case DoubleLabeledNumberType::class :
                {
                    $value = [
                        'value' => $value->getData(),
                        'text' => $value->getDataLabel(),
                        'text2' => $value->getDataLabel2()
                    ];
                    break;
                }
            default:
                {
                    $value = $value->getData();
                }
        }
        return $value;
    }

    public function getFormClassByName($name)
    {
        return $this->getTypeSmthnByName($name, 'class');
    }

    public function transformFormToNodeValue(Form $formFieldValue)
    {

        $formData = $formFieldValue->getData();
        $formType = $this->getFormBuilderClassType($formFieldValue);
        if (!in_array($formType, [
            NamedFileType::class,
            FileType::class
        ])) {

            if (empty($formData)) {
                return [];
            }

            if ($formType === CollectionType::class) {
                $formFieldValue->getConfig()->getAttribute('data_collector/passed_options')['entry_type'];
                $fieldValues = [];
                foreach ($formData as $formDatum) {
                    $fieldValues[] = $this->getFieldValuesByFormType($formDatum, $formFieldValue->getConfig()->getAttribute('data_collector/passed_options')['entry_type']);
                }
                return $fieldValues;
            } else {
                return $this->getFieldValuesByFormType($formData, $formType);
            }
        } else {
            // just pass damn FieldValue or UploadedFile already!
            // ¯\_(ツ)_/¯ //
        }

        return $formData;
    }

    public function getFormBuilderClassType(Form $formFieldValue)
    {
        return get_class($formFieldValue->getConfig()->getType()->getInnerType());
    }

    private function getFieldValuesByFormType($formData, $formType)
    {
        switch ($formType) {
            case in_array($formType, [DoubleLabeledNumberType::class, DoubleLabeledTextType::class]):
                {
                    $fieldValues = $this->makeDoubleLabeledField($formData);
                    break;
                }
            case in_array($formType, [LabeledNumberType::class, LabeledTextType::class]):
                {
                    $fieldValues = $this->makeLabeledField($formData);
                    break;
                }
            case is_array($formData):
                {
                    $fieldValues = $this->makeFieldsArray($formData);
                    break;
                }
            default:
                {
                    $fieldValues = $this->makeRegularField($formData);
                }
        }
        return $fieldValues;
    }

    function makeDoubleLabeledField($formData): FieldValueNode
    {
        $formData = array_merge(['value' => 0, 'text' => '', 'text2' => ''], $formData);
        $fv = new FieldValueNode();
        $fv->setData($this->transformFormValue($formData['value']));
        $fv->setDataLabel($this->transformFormValue($formData['text']));
        $fv->setDataLabel2($this->transformFormValue($formData['text2']));
        return $fv;
    }

    function makeLabeledField($formData): FieldValueNode
    {
        $formData = array_merge(['value' => 0, 'text' => ''], $formData);
        $fv = new FieldValueNode();
        $fv->setData($this->transformFormValue($formData['value']));
        $fv->setDataLabel($this->transformFormValue($formData['text']));
        return $fv;
    }

    function makeFieldsArray($formData): array
    {
        $fieldValues = [];
        foreach ($formData as $datum) {
            $fieldValues[] = $this->makeRegularField($datum);
        }
        return $fieldValues;
    }

    function makeRegularField($formData): FieldValueNode
    {
        $fv = new FieldValueNode();
        $fv->setData($this->transformFormValue($formData));
        return $fv;
    }

    /**
     * Horrible piece of PHP
     *
     * @param $value
     *
     * @return array|float|int|null|string
     */
    public function transformFormValue($value)
    {
        if ($value === '') {
            $value = null;
        }
        if (is_array($value)) {
            $value = array_values($value);
        }
        if ($value instanceof \DateTime) {
            $value = $value->getTimestamp();
        }
        if (is_int($value)) {
            $value = intval($value);
        } elseif (is_float($value)) {
            $value = floatval($value);
        }

        return $value;
    }

    public function getDefaultFormConfig($formType, TypeFieldNode $fieldTypeNode, $fieldValuesData)
    {
        $fieldOptions = [
            'label' => $fieldTypeNode->getName(),
            'required' => false,
            'mapped' => false,
            'error_bubbling' => false,
            'label_attr' => ['tooltip' => $fieldTypeNode->getTooltip()]
        ];

        //transform data: if this is collection or file field - pass array of values. also do that if this is labeledXType with `multiple` option
        $data = ($fieldTypeNode->isCollection() || ($formType == NamedFileType::class))
            ? $fieldValuesData
            : array_pop($fieldValuesData);
        $fieldOptions['data'] = $data;

        if ($formType == AjaxCheckboxType::class) {
            $fieldOptions['db_label'] = 'EntityTypeField';
            $fieldOptions['parent_node_val'] = $fieldTypeNode->getSlug();
        }

        if ($formType == PredefinedAjaxCheckboxType::class) {
            $predefinedFields = (array)$fieldTypeNode->getPredefinedFields();
            $fieldOptions['local_search_data'] = array_combine($predefinedFields, $predefinedFields);
        }

        if (in_array($formType, [DateTimeType::class, DateType::class, TimeType::class])) {
            $fieldOptions['widget'] = 'single_text';
        }

        if ($formType == DateType::class) {
            $fieldOptions['format'] = 'dd.MM.yyyy';
        }

        if ($fieldTypeNode->isCollection()) {
            $fieldOptions['is_multiple'] = true;
            if (in_array($formType, [NamedFileType::class, LabeledNumberType::class, LabeledTextType::class, DoubleLabeledNumberType::class, DoubleLabeledTextType::class])) {
                $fieldOptions['multiple'] = true;
            } else {
                $fieldOptions['parent_node_val'] = $fieldTypeNode->getSlug();
            }
        }

        if (in_array($formType, [NamedFileType::class, /*LabeledNumberType::class, LabeledTextType::class*/])) {
            $fieldOptions['multiple'] = true;
        }
        return $fieldOptions;
    }
}