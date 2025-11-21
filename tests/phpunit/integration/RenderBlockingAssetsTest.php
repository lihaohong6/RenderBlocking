<?php

namespace MediaWiki\Extension\RenderBlocking\Tests;

use MediaWiki\Extension\RenderBlocking\AssetType;
use MediaWiki\Extension\RenderBlocking\RenderBlockingAssets;
use MediaWikiIntegrationTestCase;

/**
 * @group RenderBlocking
 * @group Database
 * @covers \MediaWiki\Extension\RenderBlocking\RenderBlockingAssets
 */
class RenderBlockingAssetsTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->resetServices();
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

		$list = RenderBlockingAssets::getPageList( $pageName );

		$expected = [
			'MediaWiki:MyScript.js',
			'MediaWiki:MyStyle.css',
			'MediaWiki:ExplicitNamespace.js',
		];

		$this->assertEquals( $expected, $list );
	}

	public function testGetPageListReturnsEmptyOnNonExistentPage() {
		$list = RenderBlockingAssets::getPageList( 'MediaWiki:Renderblocking-nonexistent' );
		$this->assertEmpty( $list );
	}

	public function testGetAssetPageTitlesIncludesDefaultsAndDynamics() {
		$skin = 'vector';
		$type = AssetType::CSS;

		$listPage = "MediaWiki:Renderblocking-$skin-pages";
		$this->editPage( $listPage, "* ExtraSkinStyle.css" );

		$titles = RenderBlockingAssets::getAssetPageTitles( $skin, $type );

		$expected = ['MediaWiki:Renderblocking.css', "MediaWiki:Renderblocking-$skin.css", 'MediaWiki:ExtraSkinStyle.css'];

		$this->assertArrayEquals( $expected, $titles );
	}

	public function testGetAssetsFetchesContent() {
		$skin = 'vector';
		$type = AssetType::JS;

		$jsPage = "MediaWiki:Renderblocking-$skin.js";
		$jsContent = "console.log('Hello');";
		$this->editPage( $jsPage, $jsContent );

		$assets = RenderBlockingAssets::getAssets( $skin, $type );

		$this->assertArrayHasKey( $jsPage, $assets );
		$this->assertEquals( $jsContent, $assets[$jsPage] );
	}
}
