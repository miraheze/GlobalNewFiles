<?php

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

class GlobalNewFilesInsertJob extends Job {

	/** @var int */
	private $userId;

	/**
	 * @param array $params
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'GlobalNewFilesInsertJob', $title, $params );
		$this->userId = (int)$params['userId'];
	}

	/**
	 * @return bool
	 */
	public function run() {
		$services = MediaWikiServices::getInstance();

		$config = $services->getMainConfig();
		$permissionManager = $services->getPermissionManager();

		$uploadedFile = $services->getRepoGroup()->getLocalRepo()->newFile( $this->getTitle() );

		$uploadedFileTest = $services->getRepoGroup()->findFile( $this->getTitle(), [ 'ignoreRedirect' => true, 'latest' => true ] );
		$uploader = $uploadedFileTest->getUploader( File::RAW );
		if ( !$uploader ) {
			// Slightly hacky logging in production for the elusive bug, T12339
			$logger = LoggerFactory::getInstance( 'GlobalNewFiles' );
			$logger->warning( 'GlobalNewFilesInsertJob: $uploader is null for {name}', [
				'name' => $uploadedFileTest->getName(),
				'uploader' => $uploader,
				'fileTitle' => $this->getTitle(),
				'uploadedFile' => $uploadedFileTest
			] );
			$uploader = $services->getActorStore()->getUnknownActor();
			try {
				$cacheKey = $uploadedFile->getRepo()->getSharedCacheKey( 'file', sha1( $uploadedFileTest->getName() ) );
				$logger->debug( 'GlobalNewFilesInsertJob: Cache key for {name}: {cacheKey}', [
					'name' => $uploadedFileTest->getName(),
					'cacheKey' => $cacheKey,
				] );
				$ttl = null;
				$cachedData = $services->getMainWANObjectCache()->get( $cacheKey, $ttl );
				$logger->debug( 'GlobalNewFilesInsertJob: Cached data for {name} (TTL: {ttl}): {cachedData}', [
					'name' => $uploadedFileTest->getName(),
					'ttl' => $ttl,
					'cachedData' => $cachedData,
				] );
			} catch ( Exception $e ) {
				$logger->warning( 'GlobalNewFilesInsertJob: Exception when grabbing internal MediaWiki details for {name}: {exception}', [
					'name' => $uploadedFileTest->getName(),
					'exception' => $e,
				] );
			}
		}

		$dbw = GlobalNewFilesHooks::getGlobalDB( DB_PRIMARY );

		$exists = $dbw->selectRowCount(
			'gnf_files',
			'*',
			[
				'files_dbname' => WikiMap::getCurrentWikiId(),
				'files_name' => $uploadedFile->getName(),
			],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);

		if ( $exists ) {
			$dbw->update(
				'gnf_files',
				[
					'files_timestamp' => $dbw->timestamp(),
					'files_url' => $uploadedFile->getFullUrl(),
					'files_uploader' => $this->userId,
				],
				[
					'files_dbname' => WikiMap::getCurrentWikiId(),
					'files_name' => $uploadedFile->getName(),
				],
				__METHOD__
			);

			return true;
		}

		$dbw->insert(
			'gnf_files',
			[
				'files_dbname' => WikiMap::getCurrentWikiId(),
				'files_name' => $uploadedFile->getName(),
				'files_page' => $config->get( 'Server' ) . $uploadedFile->getDescriptionUrl(),
				'files_private' => (int)!$permissionManager->isEveryoneAllowed( 'read' ),
				'files_timestamp' => $dbw->timestamp(),
				'files_url' => $uploadedFile->getFullUrl(),
				'files_uploader' => $this->userId,
			],
			__METHOD__
		);

		return true;
	}
}
