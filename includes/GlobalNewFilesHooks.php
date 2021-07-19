<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

class GlobalNewFilesHooks {
	public static function onCreateWikiTables( &$tables ) {
		$tables['gnf_files'] = 'files_dbname';
	}

	public static function onUploadComplete( $uploadBase ) {
		JobQueueGroup::singleton()->push(
			new GlobalNewFilesInsertJob( [ 'uploadbase' => $uploadBase ] )
		);
	}

	public static function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		JobQueueGroup::singleton()->push(
			new GlobalNewFilesDeleteJob( [ 'file' => $file ] )
		);
	}

	public static function onPageMoveComplete( LinkTarget $old, LinkTarget $new, UserIdentity $userIdentity, int $pageid, int $redirid, string $reason, RevisionRecord $revision ) {
		$oldTitle = Title::newFromLinkTarget( $old );
		$newTitle = Title::newFromLinkTarget( $new );

		if ( $oldTitle->inNamespace( NS_FILE ) ) {
			JobQueueGroup::singleton()->push(
				new GlobalNewFilesMoveJob( [ 'oldtitle' => $oldTitle, 'newtitle' => $newTitle ] )
			);
		}
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
				__DIR__ . '/../sql/patch-gnf_files-binary.sql' 
			);
		}

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
