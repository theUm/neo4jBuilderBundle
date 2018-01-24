<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Nodeart\BuilderBundle\Helpers\FieldValueFileSaver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NamedFileType extends AbstractType
{
    protected $uploadsDir;
    protected $webUploadsDir;
    private $fieldValueFileSaver;

    public function __construct(FieldValueFileSaver $fvfs)
    {
        $this->fieldValueFileSaver = $fvfs;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'named_file';
    }

    public function getParent()
    {
        return FileType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        /**
         * Transforms
         * -TO MODEL: UploadedFile, FieldValueNode, mixed array - [UploadedFile, FieldValueNode], empty array, maybe even null
         * -FROM MODEL: just passes data - null, array of FieldValueNode`s
         */
        $builder->addViewTransformer(new CallbackTransformer(
            function ($data) { //from Model
                return $data;
            },
            function ($data) { //to Model
                $data = (!is_array($data)) ? [$data] : $data;
                $transformedData = [];
                foreach ($data as $uploadedFile) {
                    if (($uploadedFile instanceof UploadedFile)) {
                        $transformedData[] = $this->fieldValueFileSaver->moveTransformFileToNode($uploadedFile);
                    } elseif ($uploadedFile instanceof FieldValueNode) {
                        $transformedData[] = $uploadedFile;
                    }
                }

                return $transformedData;
            }));

        //prevent file deletion upon empty value.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            if (is_null($event->getData())) {
                $event->setData($event->getForm()->getData());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => false,
            'data_class' => null,
            'empty_data' => null,
            'multiple' => false,
            'is_multiple' => false,
            'object_id' => false
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['is_multiple'] = $options['is_multiple'];
        $view->vars['object_id'] = $options['object_id'];
        $view->vars['files'] = [];
        $fieldValueNodes = $form->getData();
        if (is_array($fieldValueNodes)) {
            foreach ($fieldValueNodes as $fileValueNode) {
                $view->vars['files'][] = [
                    'file_name' => $fileValueNode->getOriginalFileName(),
                    'file_url' => $fileValueNode->getWebPath(),
                    'node_id' => $fileValueNode->getId()
                ];
            }
        }
    }

}
