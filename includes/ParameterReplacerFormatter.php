<?php

namespace MediaWiki\Extension\HtmlTemplates;

use MediaWiki\Parser\Sanitizer;
use Parser;
use PPFrame;
use Wikimedia\RemexHtml\HTMLData;
use Wikimedia\RemexHtml\Serializer\HtmlFormatter;
use Wikimedia\RemexHtml\Serializer\SerializerNode;

class ParameterReplacerFormatter extends HtmlFormatter {

	/** @var Parser */
	private $parser;
	/** @var PPFrame */
	private $frame;

	private const ELM_ESC = [
		'>' => '&gt;',
		'<' => '&lt;'
	];

	/**
	 * @param array $options
	 * @param Parser $parser
	 * @param PPFrame $frame
	 */
	public function __construct( $options, $parser, $frame ) {
		parent::__construct( $options );
		$this->parser = $parser;
		$this->frame = $frame;
	}

	/** @inheritDoc */
	public function startDocument( $fragmentNS, $fragmentName ) {
		return '';
	}

	// FIXME do svg get escaped properly?

	/**
	 * @inheritDoc
	 */
	public function characters( SerializerNode $parent, $text, $start, $length ) {
		if ( !$this->shouldReplace( $text, $start, $length ) ) {
			return parent::characters( $parent, $text, $start, $length );
		}
		$string = substr( $text, $start, $length );
		// Maybe future to do is implement our own ->expand.
		// would be kind of cool to support syntax like {{arg|default|_type=bool}}
		$dom = $this->parser->preprocessToDom( $string, Parser::PTD_FOR_INCLUSION );
		if ( $parent->name === 'script' ) {
			return $this->expandUnquotedJS( $dom );
		}
		if ( $parent->name === 'style' ) {
			return strtr( $this->getStyleFrame()->expand( $dom ), self::ELM_ESC );
		}
		if ( isset( $this->rawTextElements[$parent->name] ) || $parent->name === 'pre' ) {
			$plaintext = strtr( $this->expandPlain( $dom ), self::ELM_ESC );
			return parent::characters( $parent, $plaintext, 0, strlen( $plaintext ) );
		}
		// FIXME, what about headings. Parser functions that output strip markers?
		// Custom escaping rules?
		return $this->expandWikitext( $dom );
	}

	/**
	 * Add extra per-attribute escaping
	 *
	 * @param string $name Attribute name
	 * @param string $value Attribute value
	 * @return string New value for attribute.
	 */
	private function postProcessAttr( $name, $value ) {
		switch ( $name ) {
			case 'href':
			case 'src':
				// We allow any protocol. Also relative urls starting with / or ./
				if ( !preg_match( '/^(' . wfUrlProtocols() . '|\\.?\\/)/', $value ) ) {
					return 'about:blank#NotAllowedURLProtocol';
				}
				return $value;
			default:
				return $value;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function element( SerializerNode $parent, SerializerNode $node, $contents ) {
		$name = $node->name;
		$s = "<$name";
		foreach ( $node->attrs->getValues() as $attrName => $attrValue ) {
			if ( $this->shouldReplace( $attrValue ) ) {
				$dom = $this->parser->preprocessToDom( $attrValue, Parser::PTD_FOR_INCLUSION );
				if ( $attrName === 'style' ) {
					// Special case style.
					// If whole thing is subst, we treat like wikitext
					// style tag. Otherwise we just escape ;
					if (
						substr( trim( $attrValue ), 0, 3 ) === '{{{' &&
						substr( trim( $attrValue ), -3 ) === '}}}'
					) {
						$attrValue = Sanitizer::checkCss( $this->expandPlain( $dom ) );
					} else {
						$attrValue = $this->getStyleFrame()->expand( $dom );
					}
				} else {
					$expanded = substr( $attrName, 0, 2 ) === 'on' ?
						$this->expandUnquotedJS( $dom ) :
						$this->expandPlain( $dom );
					$attrValue = $this->postProcessAttr( $attrName, $expanded );
				}
			}
			$encValue = strtr( $attrValue, $this->attributeEscapes );
			$s .= " $attrName=\"$encValue\"";
		}
		$s .= '>';
		if ( $node->namespace === HTMLData::NS_HTML ) {
			if ( isset( $contents[0] ) && $contents[0] === "\n"
				&& isset( $this->prefixLfElements[$name] )
			) {
				$s .= "\n$contents</$name>";
			} elseif ( !isset( $this->voidElements[$name] ) ) {
				$s .= "$contents</$name>";
			}
		} else {
			$s .= "$contents</$name>";
		}
		return $s;
	}

	/**
	 * Quick check to see if it is worth running replacements
	 *
	 * @param string $text (Entire string may not be used)
	 * @param int $start Where to start looking
	 * @param int|null $length Where to stop looking. Null for entire string
	 * @return bool
	 */
	private function shouldReplace( $text, $start = 0, $length = null ) {
		if ( $length === null ) {
			$length = strlen( $text );
		}
		$pos = strpos( $text, '{{', $start );
		return $pos !== false && $pos - $start < $length;
	}

	/**
	 * This is not ideal, since we are parsing args that might not be used
	 * which may modify parser and is bad for efficiency.
	 *
	 * Also different from wikitext, as individual args cannot affect each other.
	 * @return PPFrame
	 */
	private function getWikitextFrame() {
		static $wikitextFrame;
		if ( !$wikitextFrame ) {
			$wikitextFrame = $this->getProcessedFrame( [ $this->parser, 'recursiveTagParse' ], false );
		}
		return $wikitextFrame;
	}

	/**
	 * @param \PPNode $dom
	 * @return string Wikitext
	 */
	private function expandWikitext( $dom ) {
		// Todo, not clear what we should expand here, in terms of exts.
		return $this->getWikitextFrame()->expand( $dom );
	}

	/**
	 * Get a modified frame where all arguments have been quoted as JS
	 * @return PPFrame
	 */
	private function getUnquotedJSFrame() {
		static $frame;
		if ( !$frame ) {
			$frame = $this->getProcessedFrame( "Xml::encodeJSVar", true );
		}
		return $frame;
	}

	/**
	 * Get a frame for css values where ; is escaped
	 *
	 * @todo We might want to be more granular with this, for example, making
	 *  url( "foo/{{{1}}}" ) not be allowed to break out of url().
	 * @return PPFrame
	 */
	private function getStyleFrame() {
		static $frame;
		if ( !$frame ) {
			$frame = $this->getProcessedFrame(
				static function ( $val ) {
					return strtr( $val, [ ';' => '\\;' ] );
				},
				true
			);
		}
		return $frame;
	}

	/**
	 * Replace arguments escaping as javascript not inside quotes
	 *
	 * @param \PPNode $dom
	 * @return string text
	 */
	private function expandUnquotedJS( $dom ) {
		// Todo, not clear what we should expand here, in terms of exts.
		return $this->getUnquotedJSFrame()->expand( $dom );
	}

	/**
	 * Modify a frame with a callback
	 * @param callable $callback
	 * @param bool $unstrip Whether to replace strip markers. False if its still going in parser.
	 * @return PPFrame A new custom frame with the differently escaped arguments
	 */
	private function getProcessedFrame( $callback, $unstrip ) {
		$args = $this->frame->getArguments();
		if ( $unstrip ) {
			$args = array_map( [ $this->parser->getStripState(), 'unstripBoth' ], $args );
			$args = array_map( [ Sanitizer::class, 'decodeCharReferences' ], $args );
		}
		$newArgs = array_map( $callback, $args );
		return $this->parser->getPreprocessor()->newCustomFrame( $newArgs );
	}

	/**
	 * Get arguments as plaintext
	 *
	 * @return PPFrame
	 */
	private function getPlainFrame() {
		static $plainFrame;
		if ( !$plainFrame ) {
			$plainFrame = $this->getProcessedFrame( 'strval', true );
		}
		return $plainFrame;
	}

	/**
	 * Replace arguments for a plaintext context (like attributes)
	 *
	 * @warning You still may have to escape this.
	 * @param \PPNode $dom
	 * @return string text
	 */
	private function expandPlain( $dom ) {
		// Should we also do NO_IGNORE?
		return $this->getPlainFrame()->expand( $dom, PPFrame::NO_TAGS );
	}
}
