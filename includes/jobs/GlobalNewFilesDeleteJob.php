<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesDeleteJob extends Job {
	/**
	 * @param array $params
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'GlobalNewFilesDeleteJob', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$dbw->delete(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $this->getTitle()->getDBkey(),
			],
			__METHOD__
		);

		return true;
	}
}
