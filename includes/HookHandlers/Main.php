<?php

namespace Miraheze\GlobalNewFiles\HookHandlers;

use JobQueueGroup;
use JobSpecification;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesDeleteJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesInsertJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesMoveJob;

class Main implements
	FileDeleteCompleteHook,
	FileUndeleteCompleteHook,
	PageMoveCompleteHook,
	UploadCompleteHook
{

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly TitleFactory $titleFactory
	) {
	}

	/** @inheritDoc */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ) {
		$this->jobQueueGroup->push(
			new JobSpecification(
				GlobalNewFilesDeleteJob::JOB_NAME,
				[ 'fileName' => $file->getTitle()->getDBkey() ]
			)
		);
	}

	/** @inheritDoc */
	public function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ) {
		$centralUserId = $this->centralIdLookup->centralIdFromLocalUser( $user );
		$this->jobQueueGroup->push(
			new JobSpecification(
				GlobalNewFilesInsertJob::JOB_NAME,
				[
					'centralUserId' => $centralUserId,
					'fileName' => $title->getDBkey(),
				]
			)
		);
	}

	/** @inheritDoc */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$oldTitle = $this->titleFactory->newFromLinkTarget( $old );
		if ( $oldTitle->inNamespace( NS_FILE ) ) {
			$newTitle = $this->titleFactory->newFromLinkTarget( $new );
			$this->jobQueueGroup->push(
				new JobSpecification(
					GlobalNewFilesMoveJob::JOB_NAME,
					[
						'newFileName' => $newTitle->getDBkey(),
						'oldFileName' => $oldTitle->getDBkey(),
					]
				)
			);
		}
	}

	/** @inheritDoc */
	public function onUploadComplete( $uploadBase ) {
		$centralUserId = $this->centralIdLookup->centralIdFromLocalUser(
			RequestContext::getMain()->getUser()
		);

		$this->jobQueueGroup->push(
			new JobSpecification(
				GlobalNewFilesInsertJob::JOB_NAME,
				[
					'centralUserId' => $centralUserId,
					'fileName' => $uploadBase->getTitle()->getDBkey(),
				]
			)
		);
	}
}
