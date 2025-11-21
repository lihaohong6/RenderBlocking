<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;

class RenderBlockingHooks {


	private static ?Config $config = null;

	/**
	 * Since MediaWiki:renderblocking-pages controls which scripts get loaded just like
	 * MediaWiki:Gadgets-definition, it should be added to $wgRawHtmlMessages. Same
	 * goes for skin-specific pages.
	 */
	public static function setPagesAsProtected(): void {
		global $wgRawHtmlMessages;

		$wgRawHtmlMessages[] = 'renderblocking-pages';

		$skinFactory = MediaWikiServices::getInstance()->getSkinFactory();
		foreach ( $skinFactory->getInstalledSkins() as $skinName => $skinDisplayName ) {
			$wgRawHtmlMessages[] = "renderblocking-$skinName-pages";
		}
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): bool {

		if ( self::$config === null ) {
			self::$config = MediaWikiServices::getInstance()->getMainConfig();
		}

		if ( self::shouldSkipAssets( $out ) ) {
			return true;
		}

		$skinName = $skin->getSkinName();

		$stylesheets = RenderBlockingAssets::getAssets( $skinName, AssetType::CSS );
		$scripts = RenderBlockingAssets::getAssets( $skinName, AssetType::JS );

		$inlineAssets = self::$config->get( 'RenderBlockingInlineAssets' );

		if ( $inlineAssets ) {
			self::addInlineAssets( $out, $stylesheets, $scripts );
		} else {
			self::addAssetLinks(
				$out,
				$skinName,
				!empty( $stylesheets ),
				!empty( $scripts )
			);
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
		$css = RenderBlockingAssets::minifyAssets( $stylesheets, AssetType::CSS );
		if ( $css ) {
			$out->addHeadItem( 'renderblocking-css', Html::rawElement( 'style', [], $css ) );
		}

		$js = RenderBlockingAssets::minifyAssets( $scripts, AssetType::JS );
		if ( $js ) {
			$out->addHeadItem( 'renderblocking-js', Html::rawElement( 'script', [], $js ) );
		}
	}

	/**
	 * @param AssetType $type
	 * @param string $skin
	 *
	 * @return string URL to rest endpoint for assets. See RestApiRenderBlockingAssets.php
	 */
	private static function getRestUrl( AssetType $type, string $skin ): string {
		$url = wfScript( 'rest' ) . "/renderblocking/v0/assets/$type->value/$skin";

		return MediaWikiServices::getInstance()->getUrlUtils()->expand( $url, PROTO_CANONICAL );
	}

	/**
	 * @param OutputPage $out
	 * @param string $skinName
	 * @param bool $linkStylesheets Whether the stylesheet should be linked
	 * @param bool $linkScripts Whether JavaScript should be linked
	 *
	 * Add links to a stylesheet/script on a page's output
	 *
	 * @return void
	 */
	private static function addAssetLinks(
		OutputPage $out,
		string $skinName,
		bool $linkStylesheets,
		bool $linkScripts
	): void {
		if ( $linkStylesheets ) {
			$cssUrl = self::getRestUrl( AssetType::CSS, $skinName );
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
			$jsUrl = self::getRestUrl( AssetType::JS, $skinName );
			$elem = Html::rawElement( 'script', [ 'src' => $jsUrl ] );
			$out->addHeadItem( 'renderblocking-js', $elem );
		}
	}
}
