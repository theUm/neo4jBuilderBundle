<?php

namespace BuilderBundle\Entity;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 029 29.11.2016
 * Time: 11:48
 */
trait isCommentableTrait {

	/**
	 * @OGM\Property(type="boolean")
	 * @var boolean
	 */
	protected $isCommentable = true;

	/**
	 * @return bool
	 */
	public function isCommentable(): ?bool {
		return $this->isCommentable;
	}

	/**
	 * @param bool $isCommentable
	 */
	public function setIsCommentable( bool $isCommentable ) {
		$this->isCommentable = $isCommentable;
	}
}