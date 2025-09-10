<?php

namespace Miraheze\GlobalNewFiles\HookHandlers;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use Miraheze\GlobalNewFiles\Maintenance\PopulateUploaderCentralIds;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Tested by updating or installing MediaWiki.
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addTable',
			'gnf_files',
			__DIR__ . '/../../sql/gnf_files.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'modifyField',
			'gnf_files',
			'files_timestamp',
			__DIR__ . '/../../sql/patches/patch-gnf_files-binary.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addIndex',
			'gnf_files',
			'files_dbname',
			__DIR__ . '/../../sql/patches/patch-gnf_files-add-indexes.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'addField',
			'gnf_files',
			'files_uploader',
			__DIR__ . '/../../sql/patches/patch-gnf_files-add-files_uploader.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'runMaintenance',
			PopulateUploaderCentralIds::class,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'modifyField',
			'gnf_files',
			'files_uploader',
			__DIR__ . '/../../sql/patches/patch-gnf_files-modify-files_uploader-default.sql',
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-globalnewfiles',
			'dropField',
			'gnf_files',
			'files_user',
			__DIR__ . '/../../sql/patches/patch-gnf_files-drop-files_user.sql',
			true,
		] );
	}
}
