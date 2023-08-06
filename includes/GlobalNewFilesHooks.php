<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesHooks {
	/**
	 * Used for when renaming or deleting the wiki, the entry is removed
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
		MediaWikiServices::getInstance()->getJobQueueGroup()->push(
			new GlobalNewFilesInsertJob( $uploadBase->getTitle(), [] )
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
		$updater->addExtensionTable(
			'gnf_files',
			__DIR__ . '/../sql/gnf_files.sql'
		);

		$updater->modifyExtensionField(
			'gnf_files',
			'files_timestamp',
			__DIR__ . '/../sql/patches/patch-gnf_files-binary.sql'
		);

		$updater->addExtensionIndex(
			'gnf_files',
			'files_dbname',
			__DIR__ . '/../sql/patches/patch-gnf_files-add-indexes.sql'
		);
	}

	/**
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @param array|string $groups
	 * @return IDatabase
	 */
	public static function getGlobalDB( $index, $groups = [] ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
		$lb = $lbFactory->getMainLB( $config->get( 'CreateWikiDatabase' ) );

		return $lb->getMaintenanceConnectionRef( $index, $groups, $config->get( 'CreateWikiDatabase' ) );
	}
}
