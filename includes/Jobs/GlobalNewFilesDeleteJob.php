<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Miraheze\GlobalNewFiles\Hooks;

class GlobalNewFilesDeleteJob extends Job {

	public function __construct( Title $title, array $params ) {
		parent::__construct( 'GlobalNewFilesDeleteJob', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
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
