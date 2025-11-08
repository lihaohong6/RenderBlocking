<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Skin\Skin;
use Wikimedia\Minify\CSSMin;
use Wikimedia\Minify\JavaScriptMinifier;

class RenderBlockingHooks {


	private static ?Config $config = null;
	private static string $MODULE_NAME = 'ext.renderBlockingAssets';

	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$skinFactory = MediaWikiServices::getInstance()->getSkinFactory();
		$availableSkins = $skinFactory->getInstalledSkins();
		foreach ( $availableSkins as $skinName ) {
			$assets = RenderBlockingAssets::getAssets( $skinName, '.js' );
			$assets = JavaScriptMinifier::minify( implode( "\n", $assets ) );
			$resourceLoader->register( self::$MODULE_NAME . ".$skinName.js", [
				'scripts' => [
					[
						'name' => $skinName,
						'content' => $assets,
					]
				],
			] );

			$assets = RenderBlockingAssets::getAssets( $skinName, '.css' );
			$assets = CSSMIN::minify( implode( "\n", $assets ) );
			$resourceLoader->register( self::$MODULE_NAME . ".$skinName.css", [
				'styles' => [
					[
						'name' => $skinName,
						'content' => $assets,
					]
				],
			] );
		}

		return true;
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {

		if ( self::$config === null ) {
			self::$config = MediaWikiServices::getInstance()->getMainConfig();
		}

		if ( self::shouldSkipAssets( $out ) ) {
			return true;
		}

		$skinName = $skin->getSkinName();

		$stylesheets = RenderBlockingAssets::getAssets( $skinName, ".css" );
		$scripts = RenderBlockingAssets::getAssets( $skinName, ".js" );

		$inlineAssets = self::$config->get( 'RenderBlockingInlineAssets' );

		if ( $inlineAssets ) {
			self::addInlineAssets( $out, $stylesheets, $scripts );
		} else {
			self::addAssetLinks( $out, $skinName, sizeof( $stylesheets ) > 0, sizeof( $scripts ) > 0 );
		}

		return true;
	}

	private static function shouldSkipAssets( $out ): bool {
		$request = $out->getRequest();

		$safemode = $request->getVal( 'safemode' );
		if ( $safemode ) {
			return true;
		}

		if ( !self::$config->get( "AllowSiteCSSOnRestrictedPages" ) ) {
			$title = $out->getTitle();
			$skippedSpecialPages = [
				"Preferences" => true,
				"UserLogin" => true
			];
			if ( $title->isSpecialPage() && isset( $skippedSpecialPages[$title->getText()] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function addInlineAssets( OutputPage $out, array $stylesheets, array $scripts ) {
		$css = CSSMin::minify( implode( "\n", $stylesheets ) );
		if ( $css ) {
			$out->addHeadItem( 'renderblocking-css', Html::rawElement( 'style', [], $css ) );
		}

		$js = JavaScriptMinifier::minify( implode( "\n", $scripts ) );
		if ( $js ) {
			$out->addHeadItem( 'renderblocking-js', Html::rawElement( 'script', [], $js ) );
		}
	}

	private static function getModuleUrl( $moduleName ) {
		return wfAppendQuery( wfScript( 'load' ), [
			'modules' => $moduleName,
		] );
	}

	private static function addAssetLinks(
		OutputPage $out,
		string $skinName,
		bool $linkStylesheets,
		bool $linkScripts
	) {
		if ( $linkStylesheets ) {
			$cssUrl = self::getModuleUrl( self::$MODULE_NAME . ".$skinName.css" );
			$elem = Html::rawElement(
				'link',
				[
					'rel' => 'stylesheet',
					'href' => $cssUrl
				]
			);
			$out->addHeadItem( 'renderblocking-css', $elem );
		}
		if ( $linkScripts ) {
			$jsUrl = self::getModuleUrl( self::$MODULE_NAME . ".$skinName.js" );
			$elem = Html::rawElement( 'script', [ 'src' => $jsUrl ] );
			$out->addHeadItem( 'renderblocking-js', $elem );
		}
	}
}
