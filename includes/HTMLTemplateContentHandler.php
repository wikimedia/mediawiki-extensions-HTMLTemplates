<?php

namespace MediaWiki\Extension\HTMLTemplates;

use TextContentHandler;

// Unclear if this should be code or not. Main difference is language tag,
// but maybe html shouldn't be english as it may not have english text in it.

class HTMLTemplateContentHandler extends TextContentHandler {
	public const MODEL_NAME = 'htmltemplate';

	/**
	 * @inheritDoc
	 */
	public function __construct( $model = self::MODEL_NAME, $format = [ CONTENT_FORMAT_HTML ] ) {
		parent::__construct( $model, $format );
	}

	/**
	 * @inheritDoc
	 */
	protected function getContentClass() {
		return HTMLTemplateContent::class;
	}

	/**
	 * @inheritDoc
	 */
	public function makeEmptyContent() {
		return new HTMLTemplateContent( '' );
	}
}
