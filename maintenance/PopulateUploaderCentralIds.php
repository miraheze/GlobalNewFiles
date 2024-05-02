<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\WikiMap\WikiMap;

class PopulateUploaderCentralIds extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GlobalNewFiles' );
	}

	/**
	 * @inheritDoc
	 */
	public function getUpdateKey() {
		return 'GlobalNewFilesPopulateUploaderCentralIds';
	}

	/**
	 * @inheritDoc
	 */
	public function doDbUpdates() {
		$dbr = GlobalNewFilesHooks::getGlobalDB( DB_REPLICA );
		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );
		$lookup = $this->getServiceContainer()->getCentralIdLookup();
		$wikiId = WikiMap::getCurrentWikiId();

		$count = 0;
		$failed = 0;
		$stuckCount = 0;

		do {
			$bSize = ( $stuckCount + 1 ) * $this->getBatchSize();
			$res = $dbr->newSelectQueryBuilder()
				->select( 'files_user' )
				->from( 'gnf_files' )
				->where( [
					'files_uploader' => null,
					'files_dbname' => $wikiId,
				] )
				->limit( $bSize )
				->useIndex( 'files_dbname' )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( !$res->numRows() ) {
				break;
			}

			foreach ( $res as $row ) {
				$this->output( "{$row->files_user}\n" );
				$centralId = $lookup->centralIdFromName( $row->files_user, CentralIdLookup::AUDIENCE_RAW );

				if ( $centralId === 0 ) {
					$failed++;
					++$stuckCount;
					continue;
				}

				// Reset stuck counter
				$stuckCount = 0;

				$dbw->newUpdateQueryBuilder()
					->update( 'gnf_files' )
					->set( [ 'files_uploader' => $centralId ] )
					->where( [ 'files_user' => $row->files_user ] )
					->caller( __METHOD__ )
					->execute();

				$count += $dbw->affectedRows();
				$this->output( "$count\n" );
			}

			$this->waitForReplication();
		} while ( true );

		$this->output( "Completed migration, updated $count row(s), migration failed for $failed row(s).\n" );

		return true;
	}
}

$maintClass = PopulateUploaderCentralIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
