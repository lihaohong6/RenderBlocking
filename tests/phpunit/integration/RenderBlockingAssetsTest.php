<?php

namespace MediaWiki\Extension\RenderBlocking\Tests;

use MediaWiki\Extension\RenderBlocking\AssetType;
use MediaWiki\Extension\RenderBlocking\RenderBlockingAssetService;
use MediaWikiIntegrationTestCase;

/**
 * @group RenderBlocking
 * @group Database
 * @covers \MediaWiki\Extension\RenderBlocking\RenderBlockingAssetService
 */
class RenderBlockingAssetsTest extends MediaWikiIntegrationTestCase {

	private RenderBlockingAssetService $assetService;

	protected function setUp(): void {
		parent::setUp();
		$this->resetServices();
		$services = $this->getServiceContainer();
		$this->assetService = new RenderBlockingAssetService(
			$services->getMainWANObjectCache(),
			$services->getRevisionLookup(),
			$services->getTitleFactory()
		);
	}

	public function testGetPageListParsesContentCorrectly() {
		$pageName = 'MediaWiki:Renderblocking-test-list';
		$content = <<<TEXT
; This is a comment
* MyScript.js
* MyStyle.css
NotStartingWithStar.js
* MediaWiki:ExplicitNamespace.js
TEXT;

		$this->editPage( $pageName, $content );

		$list = $this->assetService->getPageList( $pageName );

		$expected = [
			'MediaWiki:MyScript.js',
			'MediaWiki:MyStyle.css',
			'MediaWiki:ExplicitNamespace.js',
		];

		$this->assertEquals( $expected, $list );
	}

	public function testGetPageListReturnsEmptyOnNonExistentPage() {
		$list = $this->assetService->getPageList( 'MediaWiki:Renderblocking-nonexistent' );
		$this->assertEmpty( $list );
	}

	public function testGetAssetPageTitlesIncludesDefaultsAndDynamics() {
		$skin = 'vector';
		$type = AssetType::CSS;

		$listPage = "MediaWiki:Renderblocking-$skin-pages";
		$this->editPage( $listPage, "* ExtraSkinStyle.css" );

		$titles = $this->assetService->getAssetPageTitles( $skin, $type );

		$expected = ['MediaWiki:Renderblocking.css', "MediaWiki:Renderblocking-$skin.css", 'MediaWiki:ExtraSkinStyle.css'];

		$this->assertArrayEquals( $expected, $titles );
	}

	public function testGetAssetsFetchesContent() {
		$skin = 'vector';
		$type = AssetType::JS;

		$jsPage = "MediaWiki:Renderblocking-$skin.js";
		$jsContent = "console.log('Hello');";
		$this->editPage( $jsPage, $jsContent );

		$assets = $this->assetService->getAssets( $skin, $type );

		$this->assertEquals( $jsContent, $assets );
	}
}
