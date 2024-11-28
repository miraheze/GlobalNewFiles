<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$fileOld = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $this->oldTitle );
		$fileNew = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $this->newTitle );

		$exists = $dbw->selectRowCount(
 			'gnf_files',
 			'*',
 			[
 				'files_dbname' => WikiMap::getCurrentWikiId(),
 				'files_name' => $fileOld->getName(),
 			],
 			__METHOD__,
 			[ 'LIMIT' => 1 ]
 		);

		if ( $exists ) {
			$dbw->update(
				'gnf_files',
				[
					'files_name' => $fileNew->getName(),
					'files_url' => $fileNew->getFullUrl(),
					'files_page' => $config->get( 'Server' ) . $fileNew->getDescriptionUrl(),
				],
				[
					'files_dbname' => WikiMap::getCurrentWikiId(),,
					'files_name' => $fileOld->getName(),
				],
				__METHOD__
			);
		}

		return true;
	}
}
