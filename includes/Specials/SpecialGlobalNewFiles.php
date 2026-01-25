<?php

namespace Miraheze\GlobalNewFiles\Specials;

use MediaWiki\Parser\ParserOptions;
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
	 * @param ?string $par @phan-unused-param
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
		$parserOptions = ParserOptions::newFromContext( $this->getContext() );
		$this->getOutput()->addParserOutputContent( $table, $parserOptions );
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'changes';
	}
}
