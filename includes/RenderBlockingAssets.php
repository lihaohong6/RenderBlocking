<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Context\RequestContext;
use MediaWiki\Page\Article;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

class RenderBlockingAssets {

	private const PAGE_PREFIX = 'MediaWiki:Renderblocking';

	static function getAssets( string $skinName, string $suffix ): array {
		$pagePrefixes = [
			self::PAGE_PREFIX
		];
		if ( $skinName ) {
			$pagePrefixes[] = self::PAGE_PREFIX . '-' . $skinName;
		}

		$result = [];
		foreach ( $pagePrefixes as $pagePrefix ) {
			$pageTitle = $pagePrefix . $suffix;
			$content = self::getPageContent( $pageTitle );
			if ( $content ) {
				$result[$pageTitle] = $content;
			}
		}

		return $result;
	}

	private static function getPageContent( string $pageName ): ?string {
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
