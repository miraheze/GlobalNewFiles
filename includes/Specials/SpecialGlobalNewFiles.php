<?php

namespace Miraheze\GlobalNewFiles\Specials;

use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use Miraheze\GlobalNewFiles\GlobalNewFilesPager;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialGlobalNewFiles extends SpecialPage {

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		private readonly IConnectionProvider $connectionProvider
	) {
		parent::__construct( 'GlobalNewFiles' );
	}

	/**
	 * @param ?string $par
	 */
	public function execute( $par ): void {
		$this->setHeaders();
		$this->outputHeader();

		$pager = new GlobalNewFilesPager(
			$this->centralIdLookup,
			$this->connectionProvider,
			$this->getContext(),
			$this->getLinkRenderer()
		);

		$this->getOutput()->addModuleStyles(
			[ 'ext.globalnewfiles.styles' ]
		);

		$table = $pager->getFullOutput();
		$this->getOutput()->addParserOutputContent( $table );
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'changes';
	}
}
