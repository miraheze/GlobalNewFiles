<?php

namespace Miraheze\GlobalNewFiles;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\IndexPager;
use MediaWiki\Pager\TablePager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use Wikimedia\Rdbms\IConnectionProvider;

class GlobalNewFilesPager extends TablePager {

	/** @inheritDoc */
	public $mDefaultDirection = IndexPager::DIR_DESCENDING;

	/**
	 * The unique sort fields for the sort options for unique paginate
	 */
	private const INDEX_FIELDS = [
		'files_timestamp' => [ 'files_timestamp' ],
		'files_dbname' => [ 'files_dbname' ],
		'files_name' => [ 'files_name' ],
	];

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
		IConnectionProvider $connectionProvider,
		IContextSource $context,
		LinkRenderer $linkRenderer
	) {
		parent::__construct( $context, $linkRenderer );
		$this->mDb = $connectionProvider->getReplicaDatabase( 'virtual-globalnewfiles' );
	}

	/** @inheritDoc */
	public function getFieldNames(): array {
		return [
			'files_timestamp' => $this->msg( 'listfiles_date' )->text(),
			'files_dbname' => $this->msg( 'globalnewfiles-label-dbname' )->text(),
			'files_name' => $this->msg( 'listfiles_name' )->text(),
			'files_url' => $this->msg( 'listfiles_thumb' )->text(),
			'files_uploader' => $this->msg( 'listfiles_user' )->text(),
		];
	}

	/** @inheritDoc */
	public function formatValue( $field, $value ): string {
		if ( $value === null ) {
			return '';
		}

		switch ( $field ) {
			case 'files_timestamp':
				$formatted = $this->escape( $this->getLanguage()->userTimeAndDate(
					$value, $this->getUser()
				) );
				break;
			case 'files_dbname':
				$formatted = $this->escape( $value );
				break;
			case 'files_url':
				$formatted = Html::element(
					'img',
					[
						'src' => $value,
						'style' => 'width: 135px; height: 135px;',
					]
				);
				break;
			case 'files_name':
				$row = $this->getCurrentRow();
				$formatted = Html::element(
					'a',
					[ 'href' => $row->files_page ],
					$value
				);
				break;
			case 'files_uploader':
				$name = $this->centralIdLookup->nameFromCentralId( (int)$value );
				$formatted = $this->getLinkRenderer()->makeLink(
					SpecialPage::getTitleFor( 'CentralAuth', $name ),
					$name
				);
				break;
			default:
				$formatted = $this->escape( "Unable to format $field" );
		}

		return $formatted;
	}

	/**
	 * Safely HTML-escapes $value
	 */
	private function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES );
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		$info = [
			'tables' => [
				'gnf_files',
			],
			'fields' => [
				'files_dbname',
				'files_name',
				'files_page',
				'files_private',
				'files_timestamp',
				'files_uploader',
				'files_url',
			],
			'conds' => [],
			'joins_conds' => [],
		];

		if ( !$this->getAuthority()->isAllowed( 'viewglobalprivatefiles' ) ) {
			$info['conds']['files_private'] = 0;
		}

		return $info;
	}

	/** @inheritDoc */
	public function getIndexField(): array {
		return [ self::INDEX_FIELDS[$this->mSort] ];
	}

	/** @inheritDoc */
	public function getDefaultSort(): string {
		return 'files_timestamp';
	}

	/** @inheritDoc */
	public function isFieldSortable( $field ): bool {
		return isset( self::INDEX_FIELDS[$field] );
	}
}
