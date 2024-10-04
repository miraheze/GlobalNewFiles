<?php

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Pager\TablePager;
use MediaWiki\SpecialPage\SpecialPage;

class GlobalNewFilesPager extends TablePager {
	/** @var LinkRenderer */
	private $linkRenderer;

	public function __construct( IContextSource $context, LinkRenderer $linkRenderer ) {
		parent::__construct( $context );

		$this->linkRenderer = $linkRenderer;

		$this->mDb = GlobalNewFilesHooks::getGlobalDB( DB_REPLICA, 'gnf_files' );

		if ( $context->getRequest()->getText( 'sort', 'files_date' ) == 'files_date' ) {
			$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		} else {
			$this->mDefaultDirection = IndexPager::DIR_ASCENDING;
		}
	}

	public function getFieldNames() {
		$headers = [
			'files_timestamp' => 'listfiles_date',
			'files_dbname'    => 'globalnewfiles-label-dbname',
			'files_name'      => 'listfiles_name',
			'files_url'       => 'listfiles_thumb',
			'files_uploader'  => 'listfiles_user',
		];

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'files_timestamp':
				$formatted = htmlspecialchars( $this->getLanguage()->userTimeAndDate( $row->files_timestamp, $this->getUser() ) );
				break;
			case 'files_dbname':
				$formatted = $row->files_dbname;
				break;
			case 'files_url':
				$formatted = Html::element(
					'img',
					[
						'src' => $row->files_url,
						'style' => 'width: 135px; height: 135px;'
					]
				);
				break;
			case 'files_name':
				$formatted = Html::element(
					'a',
					[
						'href' => $row->files_page,
					],
					$row->files_name
				);

				break;
			case 'files_uploader':
				$centralIdLookup = MediaWikiServices::getInstance()->getCentralIdLookup();
				$name = $centralIdLookup->nameFromCentralId( $row->files_uploader );

				$formatted = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CentralAuth', $name ),
					$name
				);
				break;
			default:
				$formatted = "Unable to format $name";
				break;
		}

		return $formatted;
	}

	public function getQueryInfo() {
		$info = [
			'tables' => [ 'gnf_files' ],
			'fields' => [ 'files_dbname', 'files_url', 'files_page', 'files_name', 'files_uploader', 'files_private', 'files_timestamp' ],
			'conds' => [],
			'joins_conds' => [],
		];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userHasRight( $this->getUser(), 'viewglobalprivatefiles' ) ) {
			$info['conds']['files_private'] = 0;
		}

		return $info;
	}

	public function getDefaultSort() {
		return 'files_timestamp';
	}

	public function isFieldSortable( $name ) {
		return true;
	}
}
