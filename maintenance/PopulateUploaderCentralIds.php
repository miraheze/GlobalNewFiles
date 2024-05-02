<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\MainConfigNames;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\WikiMap\WikiMap;

class PopulateUploaderCentralIds extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 1 );
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
		foreach ( $this->getConfig()->get( MainConfigNames::LocalDatabases ) as $wiki ) {
			while ( true ) {
				$res = $dbr->newSelectQueryBuilder()
					->select( 'files_user' )
					->from( 'gnf_files' )
					->where( [
						'files_uploader' => null,
						'files_dbname' => $wiki,
					] )
					->limit( $this->getBatchSize() )
					->useIndex( 'files_dbname' )
					->caller( __METHOD__ )
					->fetchResultSet();

				if ( !$res->numRows() ) {
					break;
				}

				foreach ( $res as $row ) {
					$centralId = $lookup->centralIdFromName( $row->files_user, CentralIdLookup::AUDIENCE_RAW );

					if ( $centralId === 0 ) {
						$dbw->newDeleteQueryBuilder()
							->deleteFrom( 'gnf_files' )
							->where( [
								'files_user' => $row->files_user,
								'files_dbname' => $wiki,
							] )
							->caller( __METHOD__ )
							->execute();
						continue;
					}

					$dbw->newUpdateQueryBuilder()
						->update( 'gnf_files' )
						->set( [ 'files_uploader' => $centralId ] )
						->where( [
							'files_user' => $row->files_user,
							'files_dbname' => $wiki,
						] )
						->caller( __METHOD__ )
						->execute();
				}

				$count += $dbw->affectedRows();
				$this->output( "$count\n" );
			}

			$this->output( "Completed migration for $wiki\n" );
		}

		return true;
	}
}

$maintClass = PopulateUploaderCentralIds::class;
require_once RUN_MAINTENANCE_IF_MAIN;
