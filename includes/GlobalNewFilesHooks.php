<?php

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class GlobalNewFilesHooks {

	/**
	 * Used for when renaming or deleting the wiki, the entry is removed or updated
	 * from the GlobalNewFiles table.
	 */
	public static function onCreateWikiTables( &$tables ) {
		$tables['gnf_files'] = 'files_dbname';
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		$dbw = self::getGlobalDB( DB_PRIMARY );
		$dbw->update(
			'gnf_files',
			[
				'files_private' => 1
			],
			[
				'files_dbname' => $dbname
			]
		);
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		$dbw = self::getGlobalDB( DB_PRIMARY );
		$dbw->update(
			'gnf_files',
			[
				'files_private' => 0
			],
			[
				'files_dbname' => $dbname
			]
		);
	}

	public static function onUploadComplete( $uploadBase ) {
		$file = $uploadBase->getLocalFile();
		$user = RequestContext::getMain()->getUser();

		if ( $file === null ) {
			// This should not happen, but if the $file is null then log this as a warning.
			$logger = LoggerFactory::getInstance( 'GlobalNewFiles' );
			$logger->warning( 'UploadBase::getLocalFile is null on run of UploadComplete hook.' );
		} else {
			DeferredUpdates::addCallableUpdate( static function () use ( $file, $user ) {
				$services = MediaWikiServices::getInstance();

				$config = $services->getMainConfig();
				$permissionManager = $services->getPermissionManager();

				$centralIdLookup = $services->getCentralIdLookup();

				$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );
				$dbw->insert(
					'gnf_files',
					[
						'files_dbname' => WikiMap::getCurrentWikiId(),
						'files_name' => $file->getName(),
						'files_page' => $config->get( 'Server' ) . $file->getDescriptionUrl(),
						'files_private' => (int)!$permissionManager->isEveryoneAllowed( 'read' ),
						'files_timestamp' => $dbw->timestamp(),
						'files_url' => $file->getFullUrl(),
						'files_uploader' => $centralIdLookup->centralIdFromLocalUser( $user ),
					],
					__METHOD__
				);
			} );
		}
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
