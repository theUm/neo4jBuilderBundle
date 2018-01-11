<?php

namespace Nodeart\BuilderBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Router;

class AjaxCheckboxType extends AbstractType {
	const MIN_AJAX_SEARCH_CHARS = 2;

	/**
	 * @var Router
	 */
	private $router;

	/**
	 * @param Router $router
	 */
	public function __construct( Router $router ) {
		$this->router = $router;
	}

	public function buildForm( FormBuilderInterface $builder, array $options ) {
		$isMultiple = $options['is_multiple'];
		$delimiter  = $options['delimiter'];
		$builder->addModelTransformer( new CallbackTransformer(
			function ( $value ) use ( $delimiter ) {

				if ( is_array( $value ) ) {
					$value = implode( $delimiter, $value );
				}

				return $value;
			},
            function ($value) use ($isMultiple, $delimiter, $options) {
				if ( $isMultiple && ! is_array( $value ) ) {
                    // on null input + isMultiple - return value from option "empty_data" if present. Empty array if not present
					if ( is_null( $value ) ) {
                        $value = (is_object($options['empty_data']) && ($options['empty_data'] instanceof \Closure)) ?
                            [] : $options['empty_data'];
					} else {
						$value = explode( $delimiter, $value );
					}
				}

				return $value;
			}
		) );

	}

	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix() {
		return 'ajax_checkbox';
	}

	public function getParent() {
		return HiddenType::class;
	}


	public function configureOptions( OptionsResolver $resolver ) {
		$resolver->setDefaults( [
			'db_label'          => null,
			'parent_node_val'   => '',
			'local_search_data' => [],
			'mapped'            => true,
			'is_multiple'       => false,
			'label_attr'        => [ 'tooltip' => '' ],
			'placeholder'       => '',
			'url'               => false,
			'parentField'       => false,
			'updateChilds'      => false,
			//semantic ui dropdown options
			'apiSettings'       => [ 'url' => '' ],
            'maxSelections' => 1,
			'allowAdditions'    => true,
			'minChars'          => self::MIN_AJAX_SEARCH_CHARS,
			'saveRemoteData'    => false,
			'localSearch'       => false,
			'delimiter'         => '|^',
		] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function buildView( FormView $view, FormInterface $form, array $options ) {
		$view->vars['is_multiple']       = $options['is_multiple'];
		$view->vars['local_search_data'] = $options['local_search_data'];
		$view->vars['delimiter']         = $options['delimiter'];
		$view->vars['placeholder']       = $options['placeholder'];
		if ( $options['is_multiple'] ) {
			$options['maxSelections'] = false;
		}
		//symfony config does not merge option values, just replaces them if they are present
		$view->vars['dropdown_options'] = [
			'maxSelections'   => $options['maxSelections'],
			'allowAdditions'  => $options['allowAdditions'],
			'minChars'        => $options['minChars'],
			'saveRemoteData'  => $options['saveRemoteData'],
			'delimiter'       => $options['delimiter'],
			'keys'            => [ 'delimiter' => false ],
			'parentField'     => $options['parentField'],
			'updateChilds'    => $options['updateChilds'],

			// semantic options that should not be changed
			'forceSelection'  => false,
			'selectOnKeydown' => false,
			'hideAdditions'   => false,
			'fullTextSearch' => true
		];

		//if localSearch is enabled - prevent remote ajax call on search. Use data provided in 'local_search_data'
		if ( ! $options['localSearch'] ) {
			if ( false === $options['url'] ) {
				if ( ! empty( $options['parent_node_val'] ) ) {//for 3 search params -search fieldValue of fieldType of entityType
					$url = $this->router->generate(
						'semantic_search_child',
						[ 'label' => $options['db_label'], 'parentAttrValue' => $options['parent_node_val'] ]
					);
				} else {//for 2 search params
					$url = $this->router->generate( 'semantic_search_type', [ 'label' => $options['db_label'] ] );
				}
			} else {
				$url = $options['url'];
			}
			$view->vars['dropdown_options']['apiSettings']['baseUrl'] = $url;
			$view->vars['dropdown_options']['apiSettings']['url']     = $url;
			$view->vars['dropdown_options']['apiSettings']['cache']   = false;
			$view->vars['dropdown_options']['apiSettings']['method']  = 'POST';
		}


		$view->vars['dropdown_options'] = json_encode( $view->vars['dropdown_options'], JSON_FORCE_OBJECT );
	}
}
