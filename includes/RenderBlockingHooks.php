<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\Page\Article;
use MediaWiki\Context\RequestContext;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

class RenderBlockingHooks {

	private const PAGE_PREFIX = 'MediaWiki:Renderblocking';

	private static ?Config $config = null;

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {

		if (RenderBlockingHooks::$config === null) {
			RenderBlockingHooks::$config = MediaWikiServices::getInstance()->getMainConfig();
		}
		$config = RenderBlockingHooks::$config;

		$request = $out->getRequest();

		# TODO: is this going to mess with Varnish caching?
		$safemode = $request->getVal( 'safemode' );
		if ( $safemode ) {
			return true;
		}

		if ( !$config->get("AllowSiteCSSOnRestrictedPages") ) {
			$title = $out->getTitle();
			$skippedSpecialPages = [
				"Preferences" => true,
				"UserLogin" => true
			];
			if ( $title->isSpecialPage() && isset( $skippedSpecialPages[$title->getText()] ) ) {
				return true;
			}
		}

		$skinName = $skin->getSkinName();
		$pagePrefixes = [
			self::PAGE_PREFIX
		];
		if ( $skinName ) {
			$pagePrefixes[] = self::PAGE_PREFIX . '-' . $skinName;
		}

		$stylesheets = [];
		$scripts = [];
		foreach ( $pagePrefixes as $pagePrefix ) {
			$pageTitle = $pagePrefix . '.css';
			$content = self::getPageContent( $pageTitle );
			if ( $content ) {
				$stylesheets[$pageTitle] = $content;
			}
			$pageTitle = $pagePrefix . '.js';
			$content = self::getPageContent( $pageTitle );
			if ( $content ) {
				$scripts[$pageTitle] = $content;
			}
		}

		$inlineAssets = $config->get( 'RenderBlockingInlineAssets' );

		if ( $inlineAssets ) {
			self::addInlineAssets( $out, $stylesheets, $scripts );
		} else {
			self::addAssetLinks( $out, $stylesheets, $scripts );
		}

		return true;
	}

	private static function addInlineAssets( OutputPage $out, array $stylesheets, array $scripts ) {
		$css = CSSMin::minify( implode( "\n", $stylesheets ) );
		if ( $css ) {
			$out->addHeadItem( 'render-blocking-css', Html::rawElement( 'style', [], $css ) );
		}

		$js = JavaScriptMinifier::minify( implode( "\n", $scripts ) );
		if ( $js ) {
			$out->addHeadItem( 'render-blocking-js', Html::rawElement( 'script', [], $js ) );
		}
	}

	private static function addAssetLinks( OutputPage $out ) {
		die( "unimplemented" );
		# TODO: Load this through an API which returns the
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
