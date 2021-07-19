<?php

use MediaWiki\MediaWikiServices;

class GlobalNewFilesHooks {
	public static function onCreateWikiTables( &$tables ) {
		$tables['gnf_files'] = 'files_dbname';
	}

	public static function onUploadComplete( $uploadBase ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$uploadedFile = $uploadBase->getLocalFile();

		$dbw = self::getGlobalDB( DB_PRIMARY );

		$wiki = new RemoteWiki( $config->get( 'DBname' ) );

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)$wiki->isPrivate(),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getViewURL(),
				'files_user' => $uploadedFile->getUser()
			],
			__METHOD__
		);
	}

	/**
	 * Hook to FileDeleteComplete
	 * @param File $file
	 * @param File $oldimage
	 * @param Article $article
	 * @param User $user
	 * @param string $reason
	 */
	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		$dbw = self::getGlobalDB( DB_PRIMARY );

		$dbw->delete(
			'gnf_files',
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $file->getTitle()->getDBkey(),
			],
			__METHOD__
		);
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		if ( $config->get( 'CreateWikiDatabase' ) === $config->get( 'DBname' ) ) {
			$updater->addExtensionTable(
				'gnf_files',
				__DIR__ . '/../sql/gnf_files.sql'
			);

			$updater->modifyExtensionField(
				'gnf_files',
				'files_timestamp',
				__DIR__ . '/../sql/patches/patch-gnf_files-binary.sql' 
			);

			$updater->modifyTable(
 				'gnf_files',
  				__DIR__ . '/../sql/patches/patch-gnf_files-add-indexes.sql',
				true
 			);
		}

		return true;
	}

	public static function onTitleMoveComplete( $title, $newTitle, $user, $oldid, $newid, $reason, $revision ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'globalnewfiles' );

		if ( !$title->inNamespace( NS_FILE ) ) {
			return true;
		}

		$dbw = self::getGlobalDB( DB_PRIMARY );

		$file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile( $newTitle );

		$dbw->update(
			'gnf_files',
			[
				'files_name' => $file->getName(),
				'files_url' => $file->getViewURL(),
				'files_page' => $config->get( 'Server' ) . $file->getDescriptionUrl(),
			],
			[
				'files_dbname' => $config->get( 'DBname' ),
				'files_name' => $title->getDBKey(),
			],
			__METHOD__
		);

		return true;
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

		return $lb->getConnectionRef( $index, $groups, $config->get( 'CreateWikiDatabase' ) );
	}
}
