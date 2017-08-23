<?php

namespace Nodeart\BuilderBundle\Form;

use FrontBundle\Helpers\TemplateTwigFileResolver;
use GraphAware\Neo4j\OGM\EntityManager;
use Nodeart\BuilderBundle\Form\Type\AjaxCheckboxType;
use Nodeart\BuilderBundle\Form\Type\SluggableText;
use Nodeart\BuilderBundle\Services\FormNodeBridge;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class FieldType extends AbstractType {
	private $nm;
	/**
	 * @var FormNodeBridge
	 */
	private $fnb;
	/**
	 * @var TemplateTwigFileResolver
	 */
	private $templateTwigFileResolver;

	public function __construct( EntityManager $nm, FormNodeBridge $fnb, TemplateTwigFileResolver $templateTwigFileResolver ) {
		$this->nm                       = $nm;
		$this->fnb                      = $fnb;
		$this->templateTwigFileResolver = $templateTwigFileResolver;
	}


	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm( FormBuilderInterface $builder, array $options ) {
		/**
		 * symfony field types
		 * @todo: remake this for using entity TypeFieldNode
		 */
		$fieldTypes = array_keys( FormNodeBridge::TYPES );
		$builder
			->add( 'id', HiddenType::class )
			->add( 'order', HiddenType::class, [ 'attr' => [ 'class' => 'field-order' ] ] )
			->add( 'name', TextType::class, [
				'label'    => 'Имя поля',
				'attr'     => [ 'class' => 'slug-base' ],
				'required' => true
			] )
			->add( 'slug', SluggableText::class, [
				'label'               => 'slug поля',
				'constraints'         => [
					new Length( [ 'min' => 3 ] ),
					new Regex( [
						'pattern' => '/[0-9a-zA-Z]+/',
						'message' => 'Numbers and latin characters only!'
					] )
				],
				'required'            => true,
				'base_field_selector' => '.slug-base',
			] )
			->add( 'fieldType', ChoiceType::class, [
				'label'       => 'Тип поля',
				'choices'     => array_combine( $fieldTypes, $fieldTypes ),
				'choice_attr' => function ( $val, $key, $index ) {
					//todo: if field has multi vals - ignore it (for now)
					return ( $this->fnb->isFieldHasRel( $val, FormNodeBridge::REL_IS_VARIANT_OF ) ) ? [ 'disabled' => 'disabled' ] : [];
				},
				'required'    => true
			] )
			->add( 'isCollection', CheckboxType::class, [
				'label'    => 'Множество значений',
				'required' => false
			] )
			->add( 'isMainField', CheckboxType::class, [
				'label'    => 'Поле для имени обьекта',
				'required' => false,
				'disabled' => ! $options['isDataType']

			] )
			->add( 'tabGroup', AjaxCheckboxType::class, [
				'label'             => 'Группа табов',
				'local_search_data' => $options['entityTypeFieldGroups'],
				'is_multiple'       => false,
				'localSearch'       => true,
				'maxSelections'     => 1,
				'minChars'          => 3,
				'empty_data'        => 'default'
			] )
			->add( 'predefinedFields', AjaxCheckboxType::class, [
				'label'          => 'Предустановленные значения',
				'allowAdditions' => true,
				'is_multiple'    => true,
				'localSearch'    => true,
				'minChars'       => 1,
				'empty_data'     => null
			] )
			->add( 'tooltip', TextType::class, [
				'label'      => 'Подсказка для поля. Видна при редактировании обьекта',
				'required'   => false,
				'empty_data' => ''
			] )
			->add( 'metaDesc', TextareaType::class, [
				'required'   => false,
				'empty_data' => '',
				'label'      => 'Метаданные поля (можно увидеть на отдельной станице поля)',
			] )
			->add( 'options', TextareaType::class, [
				'required'   => false,
				'empty_data' => null,
				'label'      => 'Опции поля. Индивидуальны для каждого типа поля',
			] )
			->add( 'hasOwnUrl', CheckboxType::class, [
				'label'    => 'Требуется собственный URL поля?',
				'required' => false,
			] )
			->add( 'required', CheckboxType::class, [
				'label'    => 'Обязательное поле?',
				'required' => false,
			] );

		$this->templateTwigFileResolver->addTemplateFields( $builder, 'EntityTypeField' );

		$builder->get( 'id' )
		        ->addModelTransformer( new CallbackTransformer(
			        function ( $value ) {
				        return intval( $value );
			        },
			        function ( $value ) {
				        return intval( $value );
			        }
		        ) );

		$builder->get( 'order' )
		        ->addModelTransformer( new CallbackTransformer(
			        function ( $value ) {
				        return intval( $value );
			        },
			        function ( $value ) {
				        return intval( $value );
			        }
		        ) );

		$builder->get( 'options' )
		        ->addModelTransformer( new CallbackTransformer(
			        function ( $string ) {
				        return $string;
			        },
			        function ( $string ) {
				        $json = json_decode( $string, true );
				        if ( ! is_null( $string ) && $json == null ) {
					        throw new TransformationFailedException( 'String is not a valid JSON.' );
				        }

				        return $string;
			        }
		        ) );
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( [
			'entityTypeFieldGroups' => [],
			'isDataType'            => false
		] );
	}
}
