<?php

namespace Miraheze\GlobalNewFiles;

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

	/**
	 * The unique sort fields for the sort options for unique paginate
	 */
	private const INDEX_FIELDS = [
		'files_timestamp' => [ 'files_timestamp' ],
		'files_dbname'    => [ 'files_dbname' ],
		'files_name'      => [ 'files_name' ],
	];

	public function __construct( IContextSource $context, LinkRenderer $linkRenderer ) {
		$this->linkRenderer = $linkRenderer;

		$this->mDb = Hooks::getGlobalDB( DB_REPLICA, 'gnf_files' );

		if ( $context->getRequest()->getText( 'sort', 'files_date' ) == 'files_date' ) {
			$this->mDefaultDirection = IndexPager::DIR_DESCENDING;
		} else {
			$this->mDefaultDirection = IndexPager::DIR_ASCENDING;
		}

		parent::__construct( $context, $linkRenderer );
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

	/**
	 * Safely HTML-escapes $value
	 *
	 * @param string $value
	 * @return string
	 */
	private function escape( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES );
	}

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'files_timestamp':
				$formatted = $this->escape( $this->getLanguage()->userTimeAndDate( $row->files_timestamp, $this->getUser() ) );
				break;
			case 'files_dbname':
				$formatted = $this->escape( $row->files_dbname );
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
				$formatted = $this->escape( "Unable to format $name" );
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

	public function getIndexField() {
		return [ self::INDEX_FIELDS[$this->mSort] ];
	}

	public function getDefaultSort() {
		return 'files_timestamp';
	}

	public function isFieldSortable( $field ) {
		return isset( self::INDEX_FIELDS[$field] );
	}
}
