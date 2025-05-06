<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MainConfigNames;
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

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'gnf_files' )
			->where( [
				'files_dbname' => $config->get( MainConfigNames::DBname ),
				'files_name' => $this->getTitle()->getDBkey(),
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
