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
		$config = MediaWikiServices::getInstance()->getMainConfig();

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

 		$exists = $dbw->selectRowCount(
 			'gnf_files',
 			'*',
 			[
 				'files_dbname' => WikiMap::getCurrentWikiId(),
 				'files_name' => $uploadedFile->getName(),
 			],
 			__METHOD__,
 			[ 'LIMIT' => 1 ]
 		);

		if ( $exists ) {
			$dbw->delete(
				'gnf_files',
				[
					'files_dbname' => $config->get( 'DBname' ),
					'files_name' => $this->getTitle()->getDBkey(),
				],
				__METHOD__
			);
		}

		return true;
	}
}
