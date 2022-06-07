<?php

use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\RemoteWiki;

class GlobalNewFilesInsertJob extends Job {
	/**
	 * @param array $params
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'GlobalNewFilesInsertJob', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$config = $services->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$uploadedFile = $services->getRepoGroup()->getLocalRepo()->newFile( $this->getTitle() );

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$wiki = new RemoteWiki( $config->get( 'DBname' ) );

		$uploader = $uploadedFile->getUploader( File::RAW ) ??
				MediaWikiServices::getInstance()->getActorStore()->getUnknownActor();

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)$wiki->isPrivate(),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getViewURL(),
				'files_user' => $uploader->getName(),
			],
			__METHOD__
		);

		return true;
	}
}
