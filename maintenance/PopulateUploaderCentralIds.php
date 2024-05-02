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

		do {
			$res = $dbr->newSelectQueryBuilder()
				->select( 'files_user' )
				->from( 'gnf_files' )
				->where( [
					'files_uploader' => null,
					'files_dbname' => $wikiId,
				] )
				->limit( $this->getBatchSize() )
				->useIndex( 'files_dbname' )
				->caller( __METHOD__ )
				->fetchResultSet();
			
			$this->output( "{$res->numRows()}\n" );

			foreach ( $res as $row ) {
				$centralId = $lookup->centralIdFromName( $row->files_user, CentralIdLookup::AUDIENCE_RAW );

				if ( $centralId === 0 ) {
					$failed++;
					continue;
				}

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
		} while ( $res->numRows() >= $this->getBatchSize() );

		$this->output( "Completed migration, updated $count row(s), migration failed for $failed row(s).\n" );

		return true;
	}
}

$maintClass = PopulateUploaderCentralIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
