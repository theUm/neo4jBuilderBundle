<?php

namespace Nodeart\BuilderBundle\Helpers;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Main link of structure templates and nodes.
 * Node template field is recognized by config/node_types.yml
 *
 */
class TemplateTwigFileResolver {
	const DEFAULT_TEMPLATE_NAME = '_base';
	const DEFAULT_TEMPLATE_FULL_NAME = self::DEFAULT_TEMPLATE_NAME . '.html.twig';
	const BASE_TEMPLATE_DIR_REL_PATH = __DIR__ . '/../Resources/views/Structure/';

	private $baseTwigPath;
	private $route;
	private $mapping;

	public function __construct( RequestStack $request, string $baseTwigPath, KernelInterface $kernel ) {
		$this->baseTwigPath = $baseTwigPath;
		$this->route        = $request->getCurrentRequest()->get( '_route' );
		$this->mapping      = Yaml::parse( file_get_contents( $kernel->getRootDir() . '/config/node_types.yml' ) );
	}

	/**
	 * Reads from node its field with template name.
	 * field name depends on route and config/node_types.yml
	 *
	 * @param $entity
	 * @param bool $typeToSeek
	 *
	 * @return string
	 */
	public function getTwigPathByType( $entity, $typeToSeek = false ): string {
		//base part
		$fullPath = $this->baseTwigPath;
		//type part
		$fullPath       .= $this->transformRouteToPath( $this->route ) . ':';
		$propertyGetter = 'get' . ucfirst( $this->getTemplateFieldByRoute( $this->route, $typeToSeek ) );
		//own template || base template part
		$fullPath .= ( $entity->{$propertyGetter}() ?? self::DEFAULT_TEMPLATE_FULL_NAME );

		return $fullPath;
	}

	private function transformRouteToPath( $route ) {
		// structure route names starting with 'v'
		return lcfirst( mb_substr( $route, 1 ) );
	}

	/**
	 * Seeks Entity`s field name responsive for template. Relies on current route
	 *
	 * @param $route
	 * @param null $typeToSeek
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function getTemplateFieldByRoute( $route, $typeToSeek = null ) {
		//if $typeToSeek is provided - just get fielname from config
		if ( $typeToSeek !== false && isset( $this->mapping[ $typeToSeek ]['routes'][ $route ] ) ) {
			return $this->mapping[ $typeToSeek ]['routes'][ $route ]['field'];
		}

		$routeFound = false;
		foreach ( $this->mapping as $labels ) {
			if ( isset( $labels['routes'][ $route ] ) ) {
				$routeFound = $labels['routes'][ $route ]['field'];
				break;
			}
		}
		if ( ! $routeFound ) {
			throw new \Exception( 'Cant find template field for route ' . $route . ' in config at " config/node_types.yml"' );
		}

		return $routeFound;
	}

	/**
	 * Reads config at config/node_types.yml and adds form fields with template choices
	 *
	 * @param FormBuilderInterface $formBuilder
	 * @param string $entityLabel
	 *
	 * @throws \Exception
	 */
	public function addTemplateFields( FormBuilderInterface $formBuilder, string $entityLabel ) {
		if ( ! in_array( $entityLabel, array_keys( $this->mapping ) ) ) {
			throw new \Exception( '"' . $entityLabel . '" entity label in not found in config/node_types.yml. Possible values are: ' . join( ', ', array_keys( $this->mapping ) ) );
		}
		$config = $this->mapping[ $entityLabel ];
		foreach ( $config['routes'] as $route => $routeParams ) {
			$formBuilder->add( $routeParams['field'], ChoiceType::class, [
				'label'   => $routeParams['label'],
				'choices' => $this->getPossibleTemplates( $route, true ),
				'attr'    => [ 'class' => 'twig-file-path' ],
			] );
		}
	}

	/**
	 * Seeks files in specific for template type folders.
	 * $forChoices flag used to transform array to ChoiceType form format
	 *
	 * @param $routeName
	 * @param bool $forChoices
	 *
	 * @return array
	 */
	public function getPossibleTemplates( $routeName, bool $forChoices = false ) {
		$templateFiles = [ self::DEFAULT_TEMPLATE_FULL_NAME ];
		$seekDirectory = self::BASE_TEMPLATE_DIR_REL_PATH . $this->transformRouteToPath( $routeName );
		// get rid of dots in dir, then place default template file name on first place
		$templateFiles = array_merge( $templateFiles, array_diff( scandir( $seekDirectory ), [
			'..',
			'.',
			self::DEFAULT_TEMPLATE_FULL_NAME
		] ) );
		//prepare choices for form. default template name should not be saved
		if ( $forChoices ) {
			$templateFiles                                     = array_combine( $templateFiles, $templateFiles );
			$templateFiles[ self::DEFAULT_TEMPLATE_FULL_NAME ] = null;
		}

		return $templateFiles;
	}

}