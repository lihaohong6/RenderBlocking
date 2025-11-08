<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Context\RequestContext;
use MediaWiki\Page\Article;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

class RenderBlockingAssets {

	private const PAGE_PREFIX = 'MediaWiki:Renderblocking';

	static function getAssetPageTitles( ?string $skinName, AssetType $assetType ): array {
		$result = [
			self::PAGE_PREFIX . ".$assetType->value",
		];
		if ( $skinName ) {
			$result[] = self::PAGE_PREFIX . "-$skinName.$assetType->value";
		}

		return $result;
	}

	static function getAssets( ?string $skinName, AssetType $assetType ): array {
		$result = [];
		foreach ( self::getAssetPageTitles( $skinName, $assetType ) as $pageTitle ) {
			$content = self::getPageContent( $pageTitle );
			if ( $content ) {
				$result[$pageTitle] = $content;
			}
		}

		return $result;
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
