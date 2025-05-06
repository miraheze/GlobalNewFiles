<?php

namespace Miraheze\GlobalNewFiles\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\User\CentralId\CentralIdLookup;
use Miraheze\GlobalNewFiles\Hooks;
use RuntimeException;
use Wikimedia\Rdbms\IMaintainableDatabase;

class PopulateUploaderCentralIds extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 1 );
		$this->requireExtension( 'GlobalNewFiles' );
	}

	public function getUpdateKey(): string {
		return 'GlobalNewFilesPopulateUploaderCentralIds';
	}

	public function doDBUpdates(): bool {
		$dbr = Hooks::getGlobalDB( DB_REPLICA );
		$dbw = Hooks::getGlobalDB( DB_PRIMARY );
		$lookup = $this->getServiceContainer()->getCentralIdLookup();

		if ( !( $dbr instanceof IMaintainableDatabase ) ) {
			throw new RuntimeException( 'Database class must be IMaintainableDatabase' );
		}

		if ( !$dbr->fieldExists( 'gnf_files', 'files_user', __METHOD__ ) ) {
			$this->output( 'files_user field in gnf_files table does not exist. May have already been dropped?' );
			return true;
		}

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

// @codeCoverageIgnoreStart
return PopulateUploaderCentralIds::class;
// @codeCoverageIgnoreEnd
