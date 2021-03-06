<?php

namespace Nodeart\BuilderBundle\Form;

use Nodeart\BuilderBundle\Entity\ObjectNode;
use Nodeart\BuilderBundle\Entity\UserNode;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\SluggableText;
use Nodeart\BuilderBundle\Form\Type\WysiwygType;
use Nodeart\BuilderBundle\Form\Validator\InDatabaseValidator;
use Nodeart\BuilderBundle\Helpers\TemplateTwigFileResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Router;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ObjectNodeType extends AbstractType
{
    private $templateTwigFileResolver;
    private $router;
    private $inDatabaseValidator;

    public function __construct(TemplateTwigFileResolver $templateTwigFileResolver, Router $router, InDatabaseValidator $inDatabaseValidator)
    {
        $this->templateTwigFileResolver = $templateTwigFileResolver;
        $this->router = $router;
        $this->inDatabaseValidator = $inDatabaseValidator;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @throws \Exception
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Имя конкретного объекта',
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'slug-base'
                ],
                'required' => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('slug', SluggableText::class, [
                'label' => 'Slug (Имя в ссылках)',
                'error_bubbling' => false,
                'attr' => [
                    'maxlength' => 32,
                ],
                'constraints' => [
                    new Length(['min' => 3]),
                    new Regex([
                        'pattern' => '/[0-9a-zA-Z]+/',
                        'message' => 'Numbers and latin characters only!'
                    ]),
                    new NotBlank(),
                ],
                'required' => true,
                'base_field_selector' => '.slug-base',
            ])
            ->add('description', WysiwygType::class, [
                'label' => 'Описание конкретного объекта',
                'required' => false,
                'empty_data' => ''
            ])
            ->add('isCommentable', CheckboxType::class, [
                'label' => 'Разрешить комментарии?',
                'required' => false
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус',
                'choices' => ObjectNode::STATUSES,
                'required' => true,
                'empty_data' => ObjectNode::STATUS_DRAFT
            ])
            ->add('seoTitle', TextType::class, [
                'label' => 'SEO title',
                'required' => false
            ])
            ->add('seoDescription', TextareaType::class, [
                'label' => 'SEO description',
                'required' => false,
            ])
            ->add('seoKeywords', AjaxCheckboxType::class, [
                'label' => 'SEO keywords',
                'empty_data' => null,
                'is_multiple' => true,
                'maxSelections' => false,
                'placeholder' => 'pick_field',
                'url' => $this->router->generate(
                    'semantic_search_attrib', [
                    'entityType' => 'bonuses',
                    'attr' => 'seoKeywords'
                ]),
                'error_bubbling' => false
            ])
            ->add('createdBy', AjaxCheckboxType::class, [
                'mapped' => false,
                'label' => 'Author',
                'is_multiple' => false,
                'maxSelections' => 1,
                'placeholder' => 'pick_author',
                'url' => $this->router->generate(
                    'semantic_search_user'
                ),
                'error_bubbling' => false,
            ]);

        /** @var ObjectNode $object */
        $object = $builder->getData();
        if (!is_null($object) && !is_null($object->getEntityType()) && !($object->getEntityType()->isDataType())) {
            $this->templateTwigFileResolver->addTemplateFields($builder, 'Object');
        }

        // custom transformer to transform single user
        $builder->get('createdBy')->resetViewTransformers()->addViewTransformer(
            new CallbackTransformer(
                function ($value) {
                    if (empty($value))
                        return null;
                    if ($value instanceof UserNode) {
                        return $value->getUsername();
                    }
                    return $value;
                },
                function ($value) use ($options) {
                    // on null input + isMultiple - return value from option "empty_data" if present. Empty array if not present
                    if (is_null($value)) {
                        $value = (is_object($options['empty_data']) && ($options['empty_data'] instanceof \Closure)) ?
                            null : $options['empty_data'];
                    }
                    if (empty($value))
                        return null;
                    return $value;
                }
            )
        );
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ObjectNode::class,
        ));
    }
}
