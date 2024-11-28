<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

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
				'files_name' => $this->getTitle()->getDBkey(),
			],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		if ( $exists ) {
			$dbw->delete(
				'gnf_files',
				[
					'files_dbname' => WikiMap::getCurrentWikiId(),
					'files_name' => $this->getTitle()->getDBkey(),
				],
				__METHOD__
			);
		}

		return true;
	}
}
