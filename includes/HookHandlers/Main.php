<?php

namespace Miraheze\GlobalNewFiles\HookHandlers;

use JobQueueGroup;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesDeleteJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesInsertJob;
use Miraheze\GlobalNewFiles\Jobs\GlobalNewFilesMoveJob;

class Main implements
	FileDeleteCompleteHook,
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
			new GlobalNewFilesDeleteJob(
				$file->getTitle(), []
			)
		);
	}

	/** @inheritDoc */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$oldTitle = $this->titleFactory->newFromLinkTarget( $old );
		$newTitle = $this->titleFactory->newFromLinkTarget( $new );

		if ( $oldTitle->inNamespace( NS_FILE ) ) {
			$this->jobQueueGroup->push(
				new GlobalNewFilesMoveJob( [
					'oldtitle' => $oldTitle,
					'newtitle' => $newTitle,
				] )
			);
		}
	}

	/** @inheritDoc */
	public function onUploadComplete( $uploadBase ) {
		$userId = $this->centralIdLookup->centralIdFromLocalUser(
			RequestContext::getMain()->getUser()
		);

		$this->jobQueueGroup->push(
			new GlobalNewFilesInsertJob(
				$uploadBase->getTitle(),
				[ 'userId' => $userId ]
			)
		);
	}
}
