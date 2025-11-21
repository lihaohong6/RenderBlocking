<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Skin\SkinFactory;
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
}
