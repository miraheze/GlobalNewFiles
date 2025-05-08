<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use RepoGroup;
use Wikimedia\Rdbms\IConnectionProvider;

class GlobalNewFilesInsertJob extends Job {

	public const JOB_NAME = 'GlobalNewFilesInsertJob';

	private readonly int $centralUserId;
	private readonly string $fileName;

	public function __construct(
		array $params,
		private readonly IConnectionProvider $connectionProvider,
		private readonly Config $config,
		private readonly PermissionManager $permissionManager,
		private readonly RepoGroup $repoGroup
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->centralUserId = $params['centralUserId'];
		$this->fileName = $params['fileName'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$uploadedFile = $this->repoGroup->getLocalRepo()->newFile( $this->fileName );

		$dbw = $this->connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		$exists = (bool)$dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'gnf_files' )
			->where( [
				'files_dbname' => $this->config->get( MainConfigNames::DBname ),
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
					'files_uploader' => $this->centralUserId,
				] )
				->where( [
					'files_dbname' => $this->config->get( MainConfigNames::DBname ),
					'files_name' => $uploadedFile->getName(),
				] )
				->caller( __METHOD__ )
				->execute();

			return true;
		}

		$dbw->newInsertQueryBuilder()
			->insertInto( 'gnf_files' )
			->row( [
				'files_dbname' => $this->config->get( MainConfigNames::DBname ),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $this->config->get( MainConfigNames::Server ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)!$this->permissionManager->isEveryoneAllowed( 'read' ),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getFullUrl(),
				'files_uploader' => $this->centralUserId,
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
