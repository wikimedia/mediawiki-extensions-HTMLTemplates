<?php
namespace MediaWiki\Extension\HTMLTemplates;

use MediaWiki\Content\TextContent;
use MediaWiki\Title\Title;

class HTMLTemplateContent extends TextContent {

	/** @var Title Hacky state to figure out which title we are talking about */
	private static $lastTitle;

	/**
	 * @param string $text
	 * @param string $model
	 */
	public function __construct( $text, $model = HTMLTemplateContentHandler::MODEL_NAME ) {
		parent::__construct( $text, $model );
	}

	/**
	 * @todo Can you get an XSS when doing a page preview with CSRF attack.
	 * @inheritDoc
	 */
	protected function getHtml() {
		return $this->getText();
	}

	/**
	 * This is evil hack.
	 * Lowercase t in title is to follow core.
	 * This also is called by BeforeParserFetchTemplateRevisionRecord
	 * which has same second argument, but other args are different.
	 *
	 * @param mixed $foo Ignored argument, different depending on which hook
	 * @param Title $title Title to look up
	 */
	public static function onBeforeParserFetchTemplateAndtitle( $foo, Title $title ) {
		self::$lastTitle = $title;
	}

	/**
	 * Make template transclusion work
	 *
	 * This is hacky. I wish this interface was more like a parser function
	 *
	 * @return string Wikitext
	 */
	public function getWikitextForTransclusion() {
		if ( !self::$lastTitle || self::$lastTitle->getNamespace() !== NS_HTMLTEMPLATE ) {
			return '<strong class="error">' .
				wfEscapeWikiText( wfMessage( 'htmltemplates-transclusionerror' )->inContentLanguage()->text() ) .
				'</strong>';
		}
		// Make sure we are including the right page, because we are doing evil.
		// Extensions may possibly change the fetched page in a way we are not prepared for.
		$hash = hash( 'sha256', $this->getText() );
		$pagename = self::$lastTitle->getPrefixedText();
		return '{{#renderHTMLTemplate:'
			. wfEscapeWikiText( $pagename ) . '|'
			. '_useParentParameters=true|'
			. '__hash=' . wfEscapeWikiText( $hash )
			. '}}';
	}
}
