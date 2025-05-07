<?php

namespace Miraheze\GlobalNewFiles\HookHandlers;

use Miraheze\CreateWiki\Hooks\CreateWikiStatePrivateHook;
use Miraheze\CreateWiki\Hooks\CreateWikiStatePublicHook;
use Miraheze\CreateWiki\Hooks\CreateWikiTablesHook;
use Wikimedia\Rdbms\IConnectionProvider;

class CreateWiki implements
	CreateWikiStatePrivateHook,
	CreateWikiStatePublicHook,
	CreateWikiTablesHook
{

	public function __construct(
		private readonly IConnectionProvider $connectionProvider
	) {
	}

	/** @inheritDoc */
	public function onCreateWikiStatePrivate( string $dbname ): void {
		$dbw = $this->connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [ 'files_private' => 1 ] )
			->where( [ 'files_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();
	}

	/** @inheritDoc */
	public function onCreateWikiStatePublic( string $dbname ): void {
		$dbw = $this->connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [ 'files_private' => 0 ] )
			->where( [ 'files_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();
	}

	/** @inheritDoc */
	public function onCreateWikiTables( array &$tables ): void {
		$tables['gnf_files'] = 'files_dbname';
	}
}
