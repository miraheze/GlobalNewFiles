<?php

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class MigrateOldUserColumnToActor extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Migrates data from old user ID column in the gnf_files table to the new actor column.' );
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return __CLASS__;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'The user column has already been migrated to use the actor column.';
	}

	/**
	 * Do the actual work.
	 *
	 * @return bool True to log the update as done
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );

		$res = $dbw->select(
			'gnf_files',
			[
				'files_user',
			],
			'',
			__METHOD__,
			[ 'DISTINCT' ]
		);

		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		foreach ( $res as $row ) {
			$user = $userFactory->newFromName( $row->files_user );

			if ( $user ) {
				$actorId = MediaWikiServices::getInstance()->getActorNormalization()->acquireActorId( $user, $dbw );
	
				$dbw->update(
					'gnf_files',
					[
						'files_actor' => $actorId
					],
					[
						'files_user' => $row->files_user
					],
					__METHOD__
				);
			}
		}

		return true;
	}
}

$maintClass = MigrateOldUserColumnToActor::class;
require_once RUN_MAINTENANCE_IF_MAIN;
