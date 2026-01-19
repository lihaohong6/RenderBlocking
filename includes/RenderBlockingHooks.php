<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\ResourceLoader\Module;
use MediaWiki\Skin\Skin;
use MediaWiki\Utils\UrlUtils;

class RenderBlockingHooks {


	private Config $config;
	private RenderBlockingAssetService $assetService;
	private UrlUtils $urlUtils;

	public function __construct( RenderBlockingAssetService $assetService, Config $config, UrlUtils $urlUtils ) {
		$this->assetService = $assetService;
		$this->config = $config;
		$this->urlUtils = $urlUtils;
	}

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

	public function onBeforePageDisplay( OutputPage $out, Skin $skin ): bool {

		if ( self::shouldSkipAssets( $out ) ) {
			return true;
		}

		$skinName = $skin->getSkinName();

		$stylesheets = $this->assetService->getAssets( $skinName, AssetType::CSS );
		$scripts = $this->assetService->getAssets( $skinName, AssetType::JS );

		$inlineAssets = $this->config->get( 'RenderBlockingInlineAssets' );

		if ( $inlineAssets ) {
			self::addInlineAssets( $out, $stylesheets, $scripts );
		} else {
			$debug = $out->getRequest()->getVal( 'debug' );
			self::addAssetLinks(
				$out,
				$skinName,
				!empty( $stylesheets ),
				!empty( $scripts ),
				$debug
			);
		}

		return true;
	}

	private function shouldSkipAssets( OutputPage $out ): bool {
		$request = $out->getRequest();

		$safemode = $request->getVal( 'safemode' );
		if ( $safemode ) {
			return true;
		}

		// Note that this is a setting in MediaWiki core and not an extension setting.
		if ( !$this->config->get( MainConfigNames::AllowSiteCSSOnRestrictedPages ) ) {
			// Skip for pages such as Special:Preferences and Special:Login
			if ( $out->getAllowedModules( Module::TYPE_COMBINED ) < Module::ORIGIN_USER_SITEWIDE ) {
				return true;
			}
		}

		return false;
	}

	private function addInlineAssets( OutputPage $out, string $css, string $js ): void {
		if ( $css ) {
			$out->addHeadItem( 'renderblocking-css', Html::rawElement( 'style', [], $css ) );
		}
		if ( $js ) {
			$out->addHeadItem( 'renderblocking-js', Html::rawElement( 'script', [], $js ) );
		}
	}

	/**
	 * @param AssetType $type
	 * @param string $skin
	 * @param string|null $debug Debug mode value
	 *
	 * @return string URL to rest endpoint for assets. See RestApiRenderBlockingAssets.php
	 */
	private function getRestUrl( AssetType $type, string $skin, ?string $debug = null ): string {
		$url = wfScript( 'rest' ) . "/renderblocking/v0/assets/$type->value/$skin";

		if ( $debug === 'true' || $debug === '2' ) {
			$queryParams = [ "debug" => '2' ];
			$url .= '?' . http_build_query( $queryParams );
		}

		return $this->urlUtils->expand( $url, PROTO_CANONICAL );
	}

	/**
	 * @param OutputPage $out
	 * @param string $skinName
	 * @param bool $linkStylesheets Whether the stylesheet should be linked
	 * @param bool $linkScripts Whether JavaScript should be linked
	 * @param string|null $debug Debug mode value
	 *
	 * Add links to a stylesheet/script on a page's output
	 *
	 * @return void
	 */
	private function addAssetLinks(
		OutputPage $out,
		string $skinName,
		bool $linkStylesheets,
		bool $linkScripts,
		?string $debug = null
	): void {
		if ( $linkStylesheets ) {
			$cssUrl = $this->getRestUrl( AssetType::CSS, $skinName, $debug );
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
			$jsUrl = $this->getRestUrl( AssetType::JS, $skinName, $debug );
			$elem = Html::rawElement( 'script', [ 'src' => $jsUrl ] );
			$out->addHeadItem( 'renderblocking-js', $elem );
		}
	}
}
