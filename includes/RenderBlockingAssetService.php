<?php

namespace MediaWiki\Extension\RenderBlocking;

use Exception;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

class RenderBlockingAssetService {

	private WANObjectCache $cache;
	private RevisionLookup $revisionLookup;
	private TitleFactory $titleFactory;

	public static string $SERVICE_NAME = 'RenderBlocking.AssetService';

	public function __construct(
		WANObjectCache $cache,
		RevisionLookup $revisionLookup,
		TitleFactory $titleFactory
	) {
		$this->cache = $cache;
		$this->revisionLookup = $revisionLookup;
		$this->titleFactory = $titleFactory;
	}

	public function getAssets( ?string $skinName, AssetType $assetType ): array {
		$key = $this->cache->makeKey( 'renderblocking', $skinName, $assetType->value );

		return $this->cache->getWithSetCallback( $key, 600, function () use ( $skinName, $assetType ) {
			$result = [];
			foreach ( $this->getAssetPageTitles( $skinName, $assetType ) as $pageTitle ) {
				$content = $this->getPageContent( $pageTitle );
				if ( $content ) {
					$result[$pageTitle] = $content;
				}
			}
			return $result;
		}, [ "lockTSE" => 10 ] );
	}

	private function getPageContent( string $pageName ): ?string {
		$title = $this->titleFactory->newFromText( $pageName );

		if ( !$title || !$title->exists() ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( $title );
		if ( !$revision ) {
			return null;
		}

		try {
			$content = $revision->getContent( SlotRecord::MAIN );
			return $content?->getText();
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Retrieves an array of page names listed on a wiki page.
	 * Page titles must start with a * and omit the MediaWiki
	 * namespace prefix.
	 *
	 * @param string $pageName Name of the wiki page
	 *
	 * @return array
	 */
	public function getPageList( string $pageName ): array {
		$content = $this->getPageContent( $pageName );
		if ( !$content ) {
			return [];
		}
		$result = [];
		foreach ( explode( "\n", $content ) as $line ) {
			if ( !str_starts_with( $line, '*' ) ) {
				continue;
			}
			$page = trim( substr( $line, 1 ) );
			$result[] = str_starts_with( $page, 'MediaWiki:' ) ? $page : "MediaWiki:$page";
		}
		return $result;
	}

	public function getAssetPageTitles( ?string $skinName, AssetType $assetType ): array {
		$prefixList = [ 'MediaWiki:Renderblocking' ];
		if ( $skinName ) {
			$prefixList[] = "MediaWiki:Renderblocking-$skinName";
		}
		$result = [];
		foreach ( $prefixList as $prefix ) {
			$result[] = "$prefix.{$assetType->value}";
			$extraPages = $this->getPageList( "$prefix-pages" );

			foreach ( $extraPages as $page ) {
				if ( str_ends_with( strtolower( $page ), $assetType->value ) ) {
					$result[] = $page;
				}
			}
		}
		return $result;
	}

	public function minifyAssets( array $assets, AssetType $assetType ): string {
		if ( empty( $assets ) ) {
			return "";
		}
		$combined = implode( "\n", $assets );
		return match ( $assetType ) {
			AssetType::CSS => CSSMin::minify( $combined ),
			AssetType::JS => JavaScriptMinifier::minify( $combined ),
		};
	}
}
