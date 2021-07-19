<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesInsertJob extends Job implements GenericParameterJob {
	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( 'GlobalNewFilesInsertJob', $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$uploadedFile = $this->getParams()['uploadedfile'];

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$wiki = new RemoteWiki( $config->get( 'DBname' ) );

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)$wiki->isPrivate(),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getViewURL(),
				'files_user' => $uploadedFile->getUser()
			],
			__METHOD__
		);

		return true;
	}
}
