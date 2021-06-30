<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesMoveJob extends Job implements GenericParameterJob {
	private $title;

	private $newTitle;


	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( 'GlobalNewFilesMoveJob', $params );

		$this->title = $params['title'];
		$this->newTitle = $params['newtitle'];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		if ( !$this->title->inNamespace( NS_FILE ) ) {
			return true;
		}

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
				'files_name' => $this->title->getDBKey(),
			],
			__METHOD__
		);

		return true;
	}
}
