<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Miraheze\GlobalNewFiles\Hooks;

class GlobalNewFilesInsertJob extends Job {

	/** @var int */
	private $userId;

	/**
	 * @param array $params
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'GlobalNewFilesInsertJob', $title, $params );
		$this->userId = (int)$params['userId'];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$config = $services->getMainConfig();
		$permissionManager = $services->getPermissionManager();

		$uploadedFile = $services->getRepoGroup()->getLocalRepo()->newFile( $this->getTitle() );
		$dbw = Hooks::getGlobalDB( DB_PRIMARY );

		$exists = (bool)$dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'gnf_files' )
			->where( [
				'files_dbname' => $config->get( MainConfigNames::DBname ),
				'files_name' => $uploadedFile->getName(),
			] )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchRowCount();

		if ( $exists ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'gnf_files' )
				->set( [
					'files_timestamp' => $dbw->timestamp(),
					'files_url' => $uploadedFile->getFullUrl(),
					'files_uploader' => $this->userId,
				] )
				->where( [
					'files_dbname' => $config->get( MainConfigNames::DBname ),
					'files_name' => $uploadedFile->getName(),
				] )
				->caller( __METHOD__ )
				->execute();

			return true;
		}

		$dbw->newInsertQueryBuilder()
			->insertInto( 'gnf_files' )
			->row( [
				'files_dbname' => $config->get( MainConfigNames::DBname ),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( MainConfigNames::Server ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)!$permissionManager->isEveryoneAllowed( 'read' ),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getFullUrl(),
				'files_uploader' => $this->userId,
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
