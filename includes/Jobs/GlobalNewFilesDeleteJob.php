<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MediaWikiServices;
use Miraheze\GlobalNewFiles\Hooks;

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

		$dbw = Hooks::getGlobalDB( DB_PRIMARY );

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
