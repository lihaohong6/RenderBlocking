<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\Skin\SkinFactory;
use MediaWiki\Utils\UrlUtils;
use MediaWikiIntegrationTestCase;

/**
 * @group RenderBlocking
 * @covers \MediaWiki\Extension\RenderBlocking\RenderBlockingHooks
 */
class ProtectionTest extends MediaWikiIntegrationTestCase {

	public function testRawHtmlMessagesAreSet() {
		global $wgRawHtmlMessages;
		$wgRawHtmlMessages = [];

		$dummySkins = [
			'vector' => 'Vector',
			'minerva' => 'Minerva Neue',
			'testskin' => 'Test Skin'
		];
		$mockSkinFactory = $this->createMock( SkinFactory::class );
		$mockSkinFactory->method( 'getInstalledSkins' )->willReturn( $dummySkins );

		$this->setService( 'SkinFactory', $mockSkinFactory );

		RenderBlockingHooks::setPagesAsProtected();

		$this->assertContains(
			'renderblocking-pages',
			$wgRawHtmlMessages,
			'The base renderblocking-pages key should be present.'
		);

		foreach ( $dummySkins as $name => $_ ) {
			$this->assertContains(
				"renderblocking-$name-pages",
				$wgRawHtmlMessages,
				"The $name skin key should be present."
			);
		}
	}

	/**
	 * Run the BeforePageDisplay hook with a stubbed asset service that always
	 * returns content for both CSS and JS, and return which render-blocking head
	 * items were added.
	 *
	 * @param bool $restricted Whether to mark the page as restricted via disallowUserJs()
	 * @param bool $allowSiteCss Value of $wgAllowSiteCSSOnRestrictedPages
	 * @param array $requestParams Extra request params (e.g. safemode)
	 * @return array Head item keys that were added (subset of renderblocking-css/js)
	 */
	private function runHook(
		bool $restricted,
		bool $allowSiteCss = true,
		array $requestParams = []
	): array {
		$this->overrideConfigValue( MainConfigNames::AllowSiteCSSOnRestrictedPages, $allowSiteCss );

		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $requestParams ) );
		$out = $context->getOutput();
		if ( $restricted ) {
			$out->disallowUserJs();
		}

		$assetService = $this->createMock( RenderBlockingAssetService::class );
		$assetService->method( 'getAssets' )->willReturnCallback(
			static fn ( string $skin, AssetType $type ) => "/* $type->value */"
		);

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getSkinName' )->willReturn( 'vector' );

		$hooks = new RenderBlockingHooks(
			$assetService,
			new HashConfig( [ 'RenderBlockingInlineAssets' => true ] ),
			$this->createMock( UrlUtils::class )
		);

		$hooks->onBeforePageDisplay( $out, $skin );

		return array_keys( array_intersect_key(
			$out->getHeadItemsArray(),
			[ 'renderblocking-css' => true, 'renderblocking-js' => true ]
		) );
	}

	public function testUnrestrictedPageLoadsBoth() {
		$this->assertEqualsCanonicalizing(
			[ 'renderblocking-css', 'renderblocking-js' ],
			$this->runHook( false )
		);
	}

	public function testRestrictedPageWithSiteCssLoadsCssOnly() {
		$this->assertEquals(
			[ 'renderblocking-css' ],
			$this->runHook( true, true )
		);
	}

	public function testRestrictedPageWithoutSiteCssLoadsNothing() {
		$this->assertSame( [], $this->runHook( true, false ) );
	}

	public function testSafemodeLoadsNothing() {
		$this->assertSame( [], $this->runHook( false, true, [ 'safemode' => '1' ] ) );
	}
}
