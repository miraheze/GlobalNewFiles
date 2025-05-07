<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use Wikimedia\Rdbms\IConnectionProvider;

class GlobalNewFilesDeleteJob extends Job {

	private readonly string $fileName;

	public function __construct(
		array $params,
		private readonly IConnectionProvider $connectionProvider,
		private readonly Config $config
	) {
		parent::__construct( 'GlobalNewFilesDeleteJob', $title, $params );
		$this->fileName = $params['fileName'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$dbw = $this->connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'gnf_files' )
			->where( [
				'files_dbname' => $this->config->get( MainConfigNames::DBname ),
				'files_name' => $this->fileName,
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}
}
