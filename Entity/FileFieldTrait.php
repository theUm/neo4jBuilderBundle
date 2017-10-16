<?php

namespace Nodeart\BuilderBundle\Entity;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * Created by PhpStorm.
 * User: Share
 * Date: 029 29.11.2016
 * Time: 11:48
 */
trait FileFieldTrait {

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $fileName = '';

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $originalFileName = '';

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $path = '';

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $webPath = '';

	/**
	 * @OGM\Property(type="string")
	 * @var string
	 */
	protected $mimeType = '';
	/**
	 * @var \DateTime
	 *
	 * @OGM\Property()
	 * @OGM\Convert(type="datetime", options={})
	 */
	protected $createdAt;

	/**
	 * @return string
	 */
	public function getOriginalFileName() {
		return $this->originalFileName;
	}

	/**
	 * @param string $originalFileName
	 *
	 * @return $this
	 */
	public function setOriginalFileName( string $originalFileName ) {
		$this->originalFileName = $originalFileName;

		return $this;
	}

	/**
	 * @return \Symfony\Component\Finder\SplFileInfo
	 */
	public function getFile() {
		$finder = new Finder();
		$files  = $finder
			->in( $this->getPath() )
			->name( $this->getFileName() )
			->files();
		foreach ( $files->getIterator() as $file ) {
			return $file;
		}
		throw new FileNotFoundException( 'File not found! Please upload new one' );
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param string $path
	 *
	 * @return $this
	 */
	public function setPath( string $path ) {
		$this->path = $path;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getFileName() {
		return $this->fileName;
	}

	/**
	 * @param string $fileName
	 *
	 * @return $this
	 */
	public function setFileName( string $fileName ) {
		$this->fileName = $fileName;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getWebPath() {
		return $this->webPath;
	}

	/**
	 * @param mixed $webPath
	 *
	 * @return FileFieldTrait
	 */
	public function setWebPath( $webPath ) {
		$this->webPath = $webPath;

		return $this;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * @param \DateTime|null $createdAt
	 */
	public function setCreatedAt( $createdAt ) {
		$this->createdAt = $createdAt;
	}

	/**
	 * @return string
	 */
	public function getMimeType() {
		return $this->mimeType;
	}

	/**
	 * @param string $mimeType
	 */
	public function setMimeType( string $mimeType ) {
		$this->mimeType = $mimeType;
	}
}