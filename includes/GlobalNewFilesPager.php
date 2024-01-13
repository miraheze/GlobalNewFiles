<?php

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;

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
		static $headers = null;

		$headers = [
			'files_timestamp' => 'listfiles_date',
			'files_dbname'    => 'globalnewfiles-label-dbname',
			'files_name'      => 'listfiles_name',
			'files_url'       => 'listfiles_thumb',
			'files_user'      => 'listfiles_user',
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
			case 'files_user':
				$formatted = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CentralAuth', $row->files_user ),
					$row->files_user
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
			'fields' => [ 'files_dbname', 'files_url', 'files_page', 'files_name', 'files_user', 'files_private', 'files_timestamp' ],
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
