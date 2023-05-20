<?php
namespace MediaWiki\Extension\HTMLTemplates;

use MediaWiki\MediaWikiServices;

return [
	ParameterReplacer::SERVICE_NAME => static function ( MediaWikiServices $services ): ParameterReplacer {
		return new ParameterReplacer();
	}
];
