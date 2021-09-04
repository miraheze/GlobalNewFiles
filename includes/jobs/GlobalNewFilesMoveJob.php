<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesMoveJob extends Job implements GenericParameterJob {
	/** @var Title */
	private $oldTitle;

	/** @var Title */
	private $newTitle;

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( 'GlobalNewFilesMoveJob', $params );

		$this->oldTitle = $params['oldtitle'];
		$this->newTitle = $params['newtitle'];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $this->newTitle );

		$dbw->update(
			'gnf_files',
			[
				'files_name' => $file->getName(),
				'files_url' => $file->getViewURL(),
				'files_page' => $config->get( 'Server' ) . $file->getDescriptionUrl(),
			],
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $this->oldTitle->getDBKey(),
			],
			__METHOD__
		);

		return true;
	}
}
