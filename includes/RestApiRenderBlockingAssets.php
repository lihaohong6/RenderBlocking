<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class RestApiRenderBlockingAssets extends SimpleHandler {

	private RenderBlockingAssetService $assetService;

	public function __construct( RenderBlockingAssetService $assetService ) {
		$this->assetService = $assetService;
	}

	public function run( string $assetType, string $skin ): Response {
		$type = AssetType::from( $assetType );
		$assets = $this->assetService->getAssets( $skin, $type );
		$minified = $this->assetService->minifyAssets( $assets, $type );

		$res = new Response( $minified );
		# Responses to logged-in users are always private, so this won't interfere with private wikis.
		# https://www.mediawiki.org/wiki/API:Caching_data
		$res->setHeader( 'Cache-Control', 'public,max-age=3600' );

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
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
