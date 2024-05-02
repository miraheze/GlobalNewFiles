<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

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
		$permissionManager = $services->getPermissionManager();

		$uploadedFile = $services->getRepoGroup()->getLocalRepo()->newFile( $this->getTitle() );

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$uploader = $uploadedFile->getUploader( File::RAW ) ??
				$services->getActorStore()->getUnknownActor();

		$centralIdLookup = $services->getCentralIdLookup();

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => WikiMap::getCurrentWikiId(),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)!$permissionManager->isEveryoneAllowed( 'read' ),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getViewURL(),
				'files_uploader' => $centralIdLookup->centralIdFromLocalUser( $uploader ),
			],
			__METHOD__
		);

		return true;
	}
}
