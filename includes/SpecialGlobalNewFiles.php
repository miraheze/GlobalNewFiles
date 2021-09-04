<?php

use MediaWiki\Linker\LinkRenderer;

class SpecialGlobalNewFiles extends SpecialPage {
	/** @var LinkRenderer */
	private $linkRenderer;

	public function __construct( LinkRenderer $linkRenderer ) {
		parent::__construct( 'GlobalNewFiles' );
		$this->linkRenderer = $linkRenderer;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$pager = new GlobalNewFilesPager( $this->getContext(), $this->linkRenderer );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	protected function getGroupName() {
		return 'other';
	}
}
