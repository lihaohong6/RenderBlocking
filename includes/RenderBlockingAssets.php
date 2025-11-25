<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;
use Wikimedia\ObjectCache\WANObjectCache;

class RenderBlockingAssets {

	private const PAGE_PREFIX = 'MediaWiki:Renderblocking';
	private static ?WANObjectCache $cache = null;

	/**
	 * Retrieves an array of page names listed on a wiki page.
	 * Page titles must start with a * and omit the MediaWiki
	 * namespace prefix.
	 *
	 * @param string $pageName Name of the wiki page
	 *
	 * @return array
	 */
	static function getPageList( string $pageName ): array {
		$content = self::getPageContent( $pageName );
		if ( !$content ) {
			return [];
		}
		$result = [];
		foreach ( explode( "\n", $content ) as $line ) {
			if ( !str_starts_with( $line, '*' ) ) {
				continue;
			}
			$pageName = trim( substr( $line, 1 ) );

			if ( stripos( $pageName, 'MediaWiki:' ) === 0 ) {
				$result[] = $pageName;
			} else {
				$result[] = "MediaWiki:" . $pageName;
			}
		}

		return $result;
	}

	static function filterPageList( array $pageList, AssetType $assetType ): array {
		$result = [];
		foreach ( $pageList as $pageTitle ) {
			if ( str_ends_with( $pageTitle, '.' . $assetType->value ) ) {
				$result[] = $pageTitle;
			}
		}

		return $result;
	}


	static function getAssetPageTitles( ?string $skinName, AssetType $assetType ): array {
		$prefixList = [
			self::PAGE_PREFIX
		];
		if ( $skinName ) {
			$prefixList[] = self::PAGE_PREFIX . "-$skinName";
		}
		$result = [];
		foreach ( $prefixList as $prefix ) {
			$result [] = "$prefix.$assetType->value";
			$extraPages = self::getPageList( $prefix . "-pages" );
			$extraPages = self::filterPageList( $extraPages, $assetType );
			foreach ( $extraPages as $pageTitle ) {
				$result[] = $pageTitle;
			}
		}

		return $result;
	}

	static function getAssets( ?string $skinName, AssetType $assetType ): array {
		if ( self::$cache === null ) {
			self::$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		}
		$cacheKey = self::$cache->makeKey('renderblocking', $skinName ?? "", $assetType->value );

		return self::$cache->getWithSetCallback( $cacheKey, 600, function () use ( $skinName, $assetType ) {
			$result = [];
			foreach ( self::getAssetPageTitles( $skinName, $assetType ) as $pageTitle ) {
				$content = self::getPageContent( $pageTitle );
				if ( $content ) {
					$result[$pageTitle] = $content;
				}
			}

			return $result;
		}, [ "lockTSE" => 10 ] );
	}

	static function minifyAssets( array $assets, AssetType $assetType ): string {
		if ( empty( $assets ) ) {
			return "";
		}
		$combined = implode( "\n", $assets );
		if ( $assetType == AssetType::CSS ) {
			return CSSMin::minify( $combined );
		}
		if ( $assetType == AssetType::JS ) {
			return JavaScriptMinifier::minify( $combined );
		}

		return "";
	}

	static function getPageContent( string $pageName ): ?string {
		$title = Title::newFromText( $pageName );
		if ( !$title || !$title->exists() ) {
			return null;
		}

		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$content = $article->getPage()->getContent( RevisionRecord::RAW );
		if ( !$content ) {
			return null;
		}

		$text = $content->getText();
		if ( !$text || empty( trim( $text ) ) ) {
			return null;
		}

		return $text;
	}
}
