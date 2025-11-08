<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Context\RequestContext;
use MediaWiki\Page\Article;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class RenderBlockingAssets {

	private const PAGE_PREFIX = 'MediaWiki:Renderblocking';

	static function getAssetPage( ?string $skinName, string $suffix ): string {
		if ( $skinName ) {
			return self::PAGE_PREFIX . '-' . $skinName . $suffix;
		}
		return self::PAGE_PREFIX . $suffix;
	}

	static function getAssetPageTitles( ?string $skinName, string $suffix ): array {
		$result = [
			self::getAssetPage( null, $suffix ),
		];
		if ( $skinName ) {
			$result[] = self::getAssetPage( $skinName, $suffix );
		}

		return $result;
	}

	static function getAssets( ?string $skinName, string $suffix ): array {
		$result = [];
		foreach ( self::getAssetPageTitles( $skinName, $suffix ) as $pageTitle ) {
			$content = self::getPageContent( $pageTitle );
			if ( $content ) {
				$result[$pageTitle] = $content;
			}
		}

		return $result;
	}

	static function getPageContent( string $pageName ): ?string {
		$title = Title::newFromText( $pageName );
		if ( !$title || !$title->exists() ) {
			return null;
		}

		$article = Article::newFromTitle( $title, RequestContext::getMain() );
		$content = $article->getPage()->getContent( RevisionRecord::RAW );
		$text = $content->getText();
		if ( !$text || empty( trim( $text ) ) ) {
			return null;
		}

		return $text;
	}
}
