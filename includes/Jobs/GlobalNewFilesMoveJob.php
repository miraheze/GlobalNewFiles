<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use GenericParameterJob;
use Job;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Miraheze\GlobalNewFiles\Hooks;

class GlobalNewFilesMoveJob extends Job implements GenericParameterJob {

	private readonly Title $oldTitle;
	private readonly Title $newTitle;

	public function __construct( array $params ) {
		parent::__construct( 'GlobalNewFilesMoveJob', $params );

		$this->oldTitle = $params['oldtitle'];
		$this->newTitle = $params['newtitle'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$dbw = Hooks::getGlobalDB( DB_PRIMARY );

		$fileOld = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $this->oldTitle );
		$fileNew = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $this->newTitle );

		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [
				'files_name' => $fileNew->getName(),
				'files_url' => $fileNew->getFullUrl(),
				'files_page' => $config->get( MainConfigNames::Server ) . $fileNew->getDescriptionUrl(),
			] )
			->where( [
				'files_dbname' => $config->get( MainConfigNames::DBname ),
				'files_name' => $fileOld->getName(),
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
