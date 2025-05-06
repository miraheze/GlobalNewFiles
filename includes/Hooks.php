<?php

namespace Miraheze\GlobalNewFiles;

use MediaWiki\Context\RequestContext;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesDeleteJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesInsertJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesMoveJob;
use Miraheze\GlobalNewFiles\Maintenance\PopulateUploaderCentralIds;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class Hooks {

	/**
	 * Used for when renaming or deleting the wiki, the entry is removed or updated
	 * from the GlobalNewFiles table.
	 */
	public static function onCreateWikiTables( &$tables ) {
		$tables['gnf_files'] = 'files_dbname';
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		$dbw = self::getGlobalDB( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [ 'files_private' => 1 ] )
			->where( [ 'files_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		$dbw = self::getGlobalDB( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'gnf_files' )
			->set( [ 'files_private' => 0 ] )
			->where( [ 'files_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();
	}

	public static function onUploadComplete( $uploadBase ) {
		$services = MediaWikiServices::getInstance();
		$userId = $services->getCentralIdLookup()->centralIdFromLocalUser(
			RequestContext::getMain()->getUser()
		);
		$services->getJobQueueGroup()->push(
			new GlobalNewFilesInsertJob( $uploadBase->getTitle(), [ 'userId' => $userId ] )
		);
	}

	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		MediaWikiServices::getInstance()->getJobQueueGroup()->push(
			new GlobalNewFilesDeleteJob( $file->getTitle(), [] )
		);
	}

	public static function onPageMoveComplete( $old, $new, $userIdentity, $pageid, $redirid, $reason, $revision ) {
		$oldTitle = Title::newFromLinkTarget( $old );
		$newTitle = Title::newFromLinkTarget( $new );

		if ( $oldTitle->inNamespace( NS_FILE ) ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->push(
				new GlobalNewFilesMoveJob( [ 'oldtitle' => $oldTitle, 'newtitle' => $newTitle ] )
			);
		}
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addTable',
			'gnf_files',
			__DIR__ . '/../sql/gnf_files.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'modifyField',
			'files_timestamp',
			__DIR__ . '/../sql/patches/patch-gnf_files-binary.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addIndex',
			'gnf_files',
			'files_dbname',
			__DIR__ . '/../sql/patches/patch-gnf_files-add-indexes.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addField',
			'gnf_files',
			'files_uploader',
			__DIR__ . '/../sql/patches/patch-gnf_files-add-files_uploader.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'runMaintenance',
			PopulateUploaderCentralIds::class,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'modifyField',
			'gnf_files',
			'files_uploader',
			__DIR__ . '/../sql/patches/patch-gnf_files-modify-files_uploader-default.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'dropField',
			'gnf_files',
			'files_user',
			__DIR__ . '/../sql/patches/patch-gnf_files-drop-files_user.sql',
			true,
		] );
	}

	/**
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @param string|null $group
	 * @return IDatabase|IReadableDatabase
	 */
	public static function getGlobalDB( int $index, ?string $group = null ): IDatabase|IReadableDatabase {
		$connectionProvider = MediaWikiServices::getInstance()->getConnectionProvider();

		if ( $index === DB_PRIMARY ) {
			return $connectionProvider->getPrimaryDatabase( 'virtual-globalnewfiles' );
		}

		return $connectionProvider->getReplicaDatabase( 'virtual-globalnewfiles', $group );
	}
}
