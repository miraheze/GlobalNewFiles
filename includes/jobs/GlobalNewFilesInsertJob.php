<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

class GlobalNewFilesInsertJob extends Job {
	/**
	 * @param array $params
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'GlobalNewFilesInsertJob', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$config = $services->getMainConfig();
		$permissionManager = $services->getPermissionManager();

		$uploadedFile = $services->getRepoGroup()->getLocalRepo()->newFile( $this->getTitle() );

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$uploader = $uploadedFile->getUploader( File::RAW );
		if ( $uploader === null ) {
			$uploader = $services->getActorStore()->getUnknownActor();

			// Slightly hacky logging in production for the elusive bug, T12339
			$logger = LoggerFactory::getInstance( 'GlobalNewFiles' );
			$logger->warning( 'GlobalNewFilesInsertJob: $uploader is null for {name}', [
				'name' => $uploadedFile->getName(),
			] );

			try {
				$cacheKey = $uploadedFile->getRepo()->getSharedCacheKey( 'file', sha1( $uploadedFile->getName() ) );
				$logger->debug( 'GlobalNewFilesInsertJob: Cache key for {name}: {cacheKey}', [
					'name' => $uploadedFile->getName(),
					'cacheKey' => $cacheKey,
				] );

				$ttl = null;
				$cachedData = $services->getMainWANObjectCache()->get( $cacheKey, $ttl );
				$logger->debug( 'GlobalNewFilesInsertJob: Cached data for {name} (TTL: {ttl}): {cachedData}', [
					'name' => $uploadedFile->getName(),
					'ttl' => $ttl,
					'cachedData' => $cachedData,
				] );
			} catch ( Exception $e ) {
				$logger->warning( 'GlobalNewFilesInsertJob: Exception when grabbing internal MediaWiki details for {name}: {exception}', [
					'name' => $uploadedFile->getName(),
					'exception' => $e,
				] );
			}
		}

		$centralIdLookup = $services->getCentralIdLookup();

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => WikiMap::getCurrentWikiId(),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)!$permissionManager->isEveryoneAllowed( 'read' ),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getFullUrl(),
				'files_uploader' => $centralIdLookup->centralIdFromLocalUser( $uploader ),
			],
			__METHOD__
		);

		return true;
	}
}
