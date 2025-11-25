<?php

use MediaWiki\Extension\RenderBlocking\RenderBlockingAssetService;
use MediaWiki\MediaWikiServices;

return [
	RenderBlockingAssetService::$SERVICE_NAME => function ( MediaWikiServices $services ) {
		return new RenderBlockingAssetService(
			$services->getMainWANObjectCache(),
			$services->getRevisionLookup(),
			$services->getTitleFactory()
		);
	},
];
