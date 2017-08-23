<?php
/**
 * Created by PhpStorm.
 * User: Share
 * Date: 021 21.10.2016
 * Time: 16:14
 */

namespace BuilderBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends Controller {

	/**
	 * @Route("/builder/menu/add", name="menu_add")
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function menuEditAction() {
		return $this->render( 'BuilderBundle:Menu:add.html.twig' );
	}


}