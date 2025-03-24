<?php

namespace MediaWiki\Extension\HTMLTemplates;

use LogicException;
use MediaWiki\Hook\EditPageBeforeEditButtonsHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

class Hooks implements
	ParserFirstCallInitHook,
	ContentHandlerDefaultModelForHook,
	EditPageBeforeEditButtonsHook
{

	/** @var RevisionLookup */
	private $revisionLookup;
	/** @var ParameterReplacer */
	private $parameterReplacer;

	/**
	 * @param RevisionLookup $revisionLookup
	 * @param ParameterReplacer $parameterReplacer
	 */
	public function __construct( RevisionLookup $revisionLookup, ParameterReplacer $parameterReplacer ) {
		$this->revisionLookup = $revisionLookup;
		$this->parameterReplacer = $parameterReplacer;
	}

	/**
	 * @inheritDoc
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'renderhtmltemplate',
			[ $this, 'renderHTMLTemplate' ],
			Parser::SFH_OBJECT_ARGS
		);
	}

	/**
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param \PPNode[] $args
	 * @return string|array Standard parser func return
	 */
	public function renderHTMLTemplate( Parser $parser, PPFrame $frame, $args ) {
		if ( !count( $args ) ) {
			// Eventually replace with ->msg
				return '<strong>' .
				wfEscapeWikiText( wfMessage( 'htmltemplates-transclusion-args' )->inContentLanguage()->text() ) .
				'</strong>';
		}
		$pageName = trim( $frame->expand( array_shift( $args ) ) );
		$title = Title::newFromText( $pageName, NS_HTMLTEMPLATE );
		if ( !$title || !$title->inNamespace( NS_HTMLTEMPLATE ) || !$title->hasContentModel( 'htmltemplate' ) ) {
			return '<strong>' .
				wfEscapeWikiText( wfMessage( 'htmltemplates-transclusion-args' )->inContentLanguage()->text() ) .
				'</strong>';
		}
		$hash = null;
		$argFrame = $frame;
		foreach ( $args as $arg ) {
			$bits = $arg->splitArg();
			if ( (string)$bits['index'] === '' ) {
				$name = trim( $frame->expand( $bits['name'], PPFrame::STRIP_COMMENTS ) );
				switch ( $name ) {
					case '_useParentParameters':
					// FIXME in future user should be able to use parser func directly.
					// $argFrame = $frame->getParent();
						break;
					case '__hash':
						$hash = trim( $frame->expand( $bits['value'] ) );
						break;
				}
			}
		}
		// FIXME, should we use parser to fetch for user permissions/flagged revs?
		$rev = $this->revisionLookup->getRevisionByTitle( $title );
		$content = $rev->getContent( SlotRecord::MAIN );
		if ( !$content instanceof HTMLTemplateContent ) {
			throw new LogicException( "Expected HTMLTemplateContent" );
		}
		$text = $content->getText();
		if ( $hash !== null && $hash !== hash( 'sha256', $text ) ) {
			return '<strong>' .
			wfEscapeWikiText( wfMessage( 'htmltemplates-transclusion-args' )->inContentLanguage()->text() ) .
			'</strong>';
		}
		$replaced = $this->parameterReplacer->replace( $text, $parser, $frame );
		return [
			'text' => $replaced,
			// This uses a general strip item. Would a nowiki strip item make more sense?
			'isHTML' => true
		];
	}

	/**
	 * @param Title $title Page title in question
	 * @param string &$lang What computer language is this page
	 * @param string $model The content model
	 * @param string $format The content format
	 */
	public function onCodeEditorGetPageLanguage( $title, &$lang, $model, $format ) {
		if ( $model === 'htmltemplate' ) {
			$lang = 'html';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( $title->getNamespace() === NS_HTMLTEMPLATE ) {
			if ( $title->isSubpage() && substr( $title->getText(), -4 ) === '/doc' ) {
				return;
			}
			$model = 'htmltemplate';
		}
	}

	/**
	 * @inheritDoc
	 *
	 * Disable preview button. For security reasons we don't want previews
	 * to actually work (in case of CSRF).
	 */
	public function onEditPageBeforeEditButtons( $editor, &$buttons, &$tabindex ) {
		if ( $editor->getTitle()->hasContentModel( HTMLTemplateContentHandler::MODEL_NAME ) ) {
			unset( $buttons['preview'] );
		}
	}

	/**
	 * Setup some aliases on extension registration.
	 * compat with 1.39
	 *
	 * @suppress PhanUndeclaredClassReference
	 * @todo Eventually remove.
	 */
	public static function setup() {
		if (
			!class_exists( \RemexHtml\HTMLData::class ) &&
			class_exists( \Wikimedia\RemexHtml\HTMLData::class )
		) {
			class_alias(
				\Wikimedia\RemexHtml\Serializer\HtmlFormatter::class,
				\RemexHtml\Serializer\HtmlFormatter::class
			);
			class_alias(
				\Wikimedia\RemexHtml\Serializer\SerializerNode::class,
				\RemexHtml\Serializer\SerializerNode::class
			);
			class_alias(
				\Wikimedia\RemexHtml\Serializer\Serializer::class,
				\RemexHtml\Serializer\Serializer::class
			);
			class_alias(
				\Wikimedia\RemexHtml\Tokenizer\Tokenizer::class,
				\RemexHtml\Tokenizer\Tokenizer::class
			);
			class_alias(
				\Wikimedia\RemexHtml\TreeBuilder\Dispatcher::class,
				\RemexHtml\TreeBuilder\Dispatcher::class
			);
			class_alias(
				\Wikimedia\RemexHtml\TreeBuilder\TreeBuilder::class,
				\RemexHtml\TreeBuilder\TreeBuilder::class
			);
			class_alias(
				\Wikimedia\RemexHtml\TreeBuilder\Element::class,
				\RemexHtml\TreeBuilder\Element::class
			);
			class_alias(
				\Wikimedia\RemexHtml\TreeBuilder\RelayTreeHandler::class,
				\RemexHtml\TreeBuilder\RelayTreeHandler::class
			);
			class_alias(
				\Wikimedia\RemexHtml\TreeBuilder\TreeHandler::class,
				\RemexHtml\TreeBuilder\TreeHandler::class
			);
			class_alias(
				\Wikimedia\RemexHtml\HTMLData::class,
				\RemexHtml\HTMLData::class
			);
		}
	}
}
