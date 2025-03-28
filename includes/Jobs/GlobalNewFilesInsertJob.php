<?php

namespace Miraheze\GlobalNewFiles\Jobs;

use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Miraheze\GlobalNewFiles\Hooks;

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

		$dbw = Hooks::getGlobalDB( DB_PRIMARY );

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
