<?php

namespace MediaWiki\Extension\HTMLTemplates;

use Content;
use LogicException;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use TextContentHandler;

// Unclear if this should be code or not. Main difference is language tag,
// but maybe html shouldn't be english as it may not have english text in it.

class HTMLTemplateContentHandler extends TextContentHandler {
	public const MODEL_NAME = 'htmltemplate';

	private ParserFactory $parserFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		$model,
		ParserFactory $parserFactory
	) {
		$this->parserFactory = $parserFactory;
		$format = [ CONTENT_FORMAT_HTML ];
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

	/**
	 * @inheritDoc
	 */
	public function canBeUsedOn( Title $title ) {
		if ( $title->getNamespace() !== NS_HTMLTEMPLATE ) {
			return false;
		}
		return parent::canBeUsedOn( $title );
	}

	/**
	 * @inheritDoc
	 */
	public function supportsPreloadContent(): bool {
		// Sounds scary security wise
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function fillParserOutput(
		Content $content,
		ContentParseParams $cpo,
		ParserOutput &$parserOutput
	) {
		// Based on scribunto
		$poptions = $cpo->getParserOptions();
		$curTitle = Title::newFromPageReference( $cpo->getPage() );
		// MW core has a built in unit test that breaks with this
		if ( !defined( 'MW_PHPUNIT_TEST' ) && $curTitle->getNamespace() !== NS_HTMLTEMPLATE ) {
			throw new LogicException( "Wrong ns" );
		}
		$docTitle = Title::makeTitleSafe( NS_HTMLTEMPLATE, $curTitle->getText() . '/doc' );
		if ( !$docTitle ) {
			// FIXME handle this case better. I guess if title is too long.
			// Not making an i18n message for now because i think i will change
			// this later.
			$parserOutput->setText( '<span class="error">Could not display</span>' );
			return;
		}
		if ( $poptions->getIsPreview() ) {
			// Not making an i18n message as this should not be reachable.
			// This is a paranoia measure against CSRF based XSS.
			$parserOutput->setText( '<span class="error">Preview is not available</span>' );
			return;
		}
		$docMsg = wfMessage(
			$docTitle->exists() ? 'htmltemplate-doc-show' : 'htmltemplate-doc-missing',
			$docTitle->getPrefixedText(),
			$curTitle->getPrefixedText()
		)->inContentLanguage();
		$parser = $this->parserFactory->getInstance();
		if ( $poptions->getTargetLanguage() === null ) {
			$poptions->setTargetLanguage( $docTitle->getPageLanguage() );
		}
		$parserOutput = $parser->parse( $docMsg->plain(), $cpo->getPage(), $poptions, true, true, $cpo->getRevId() );
	}
}
