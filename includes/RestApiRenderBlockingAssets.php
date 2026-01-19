<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Skin\SkinFactory;
use Wikimedia\ParamValidator\ParamValidator;

class RestApiRenderBlockingAssets extends SimpleHandler {

	private RenderBlockingAssetService $assetService;
	private SkinFactory $skinFactory;

	public function __construct( RenderBlockingAssetService $assetService, SkinFactory $skinFactory ) {
		$this->assetService = $assetService;
		$this->skinFactory = $skinFactory;
	}

	public function run( string $assetType, string $skin ): Response {
		$params = $this->getValidatedParams();
		$type = AssetType::from( $assetType );
		$isDebugMode = $params['debug'] === 'true' || $params['debug'] === '2';
		$assets = $this->assetService->getAssets( $skin, $type, $isDebugMode );

		$res = new Response( $assets );
		# Responses to logged-in users always have Cache-Control marked as private,
		# so this won't interfere with private wikis.
		# https://www.mediawiki.org/wiki/API:Caching_data
		# MediaWiki core serves the content of Common.js and Common.css even in the absence of read rights, so
		# the possibility of leaking ender-blocking css/js is fine.
		if ( $isDebugMode ) {
			$res->setHeader( 'Cache-Control', 'private,no-cache,no-store' );
		} else {
			$res->setHeader( 'Cache-Control', 'public,max-age=3600' );
		}

		$contentType = $type == AssetType::CSS ? "text/css" : "text/javascript";
		$res->setHeader( 'Content-Type', "$contentType; charset=utf-8" );

		return $res;
	}

	public function needsReadAccess(): bool {
		return true;
	}

	public function needsWriteAccess(): bool {
		return false;
	}

	public function getParamSettings(): array {
		return [
			'asset_type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => [
					'css',
					'js'
				],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'skin' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => array_keys( $this->skinFactory->getInstalledSkins() ),
				ParamValidator::PARAM_REQUIRED => true,
			],
			'debug' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}
}
