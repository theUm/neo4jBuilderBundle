<?php

namespace Nodeart\BuilderBundle\Helpers;

use Nodeart\BuilderBundle\Entity\FieldValueNode;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;


/**
 * Created by PhpStorm.
 * User: Share
 * Date: 011 11.10.2016
 * Time: 18:16
 */
class FieldValueFileSaver {
	private $uploadsDir;
	private $webUploadsDir;

	public function __construct( string $uploads_dir, string $web_uploads_dir ) {
		$this->uploadsDir    = $uploads_dir;
		$this->webUploadsDir = $web_uploads_dir;
	}

	/**
	 * @return string
	 */
	public function getUploadsDir(): string {
		return $this->uploadsDir;
	}

	/**
	 * @return string
	 */
	public function getWebUploadsDir(): string {
		return $this->webUploadsDir;
	}

	public function moveTransformFileToNode( UploadedFile $data, $fieldValueNode = null ) {
		//if file successfully written to temp dir
		if ( $data->isValid() ) {
			$fieldValueNode = $fieldValueNode ?? new FieldValueNode();
			if ( is_null( $fieldValueNode->getId() ) ) {
				// Generate a unique name for the file before saving it
				/** @var UploadedFile $data */
				$fileName = md5( uniqid() ) . '.' . $data->guessExtension();
			} else {
				//replace image
				$fileName = $fieldValueNode->getFileName();
			}
			try {
				$mimeType = $data->getMimeType();
				// Move the file
				$data->move(
					$this->uploadsDir,
					$fileName
				);
				$fieldValueNode->setFileName( $fileName );
				$fieldValueNode->setOriginalFileName( $data->getClientOriginalName() );
				$fieldValueNode->setPath( $this->uploadsDir );
				$fieldValueNode->setWebPath( $this->webUploadsDir . $fileName );
				$fieldValueNode->setCreatedAt( new \DateTime() );
				$fieldValueNode->setMimeType( $mimeType );
				$data = $fieldValueNode;
			} catch ( FileException $e ) {
				throw new TransformationFailedException( 'File handling exception: ' . $e->getMessage() );
			}
		}

		return $data;
	}

}