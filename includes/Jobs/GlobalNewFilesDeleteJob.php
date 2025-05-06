<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class GlobalNewFilesDeleteJob extends Job {

	public function __construct( Title $title, array $params ) {
		parent::__construct( 'GlobalNewFilesDeleteJob', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbw = $connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );

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
