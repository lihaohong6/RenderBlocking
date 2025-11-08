<?php

namespace MediaWiki\Extension\RenderBlocking;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class RestApiRenderBlockingAssets extends SimpleHandler {

	public function run( string $assetType, string $skin ): Response {
		$type = AssetType::from( $assetType );
		$assets = RenderBlockingAssets::getAssets( $skin, $type );
		$minified = RenderBlockingAssets::minifyAssets( $assets, $type );

		$res = new Response( $minified );
		# TODO: does this caching setting interfere with private wikis?
		#  How about cache invalidation?
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
