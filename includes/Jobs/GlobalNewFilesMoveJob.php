<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use RepoGroup;
use Wikimedia\Rdbms\IConnectionProvider;

class GlobalNewFilesMoveJob extends Job {

	public const JOB_NAME = 'GlobalNewFilesMoveJob';

	private readonly string $newFileName;
	private readonly string $oldFileName;

	public function __construct(
		array $params,
		private readonly IConnectionProvider $connectionProvider,
		private readonly Config $config,
		private readonly RepoGroup $repoGroup
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->oldFileName = $params['oldFileName'];
		$this->newFileName = $params['newFileName'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$oldFile = $this->repoGroup->getLocalRepo()->newFile( $this->oldFileName );
		$newFile = $this->repoGroup->getLocalRepo()->newFile( $this->newFileName );

		$dbw = $this->connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [
				'files_name' => $newFile->getName(),
				'files_url' => $newFile->getFullUrl(),
				'files_page' => $this->config->get( MainConfigNames::Server ) . $newFile->getDescriptionUrl(),
			] )
			->where( [
				'files_dbname' => $this->config->get( MainConfigNames::DBname ),
				'files_name' => $oldFile->getName(),
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
