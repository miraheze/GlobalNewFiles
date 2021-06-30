<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesDeleteJob extends Job implements GenericParameterJob {
	private $file;

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( 'GlobalNewFilesDeleteJob', $params );

		$this->file = $params['file'];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$dbw = self::getGlobalDB( DB_PRIMARY );

		$dbw->delete(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $this->file->getTitle()->getDBkey(),
			],
			__METHOD__
		);

		return true;
	}
}
