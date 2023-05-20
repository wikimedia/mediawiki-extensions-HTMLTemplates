<?php
namespace MediaWiki\Extension\HTMLTemplates;

use Parser;
use PPFrame;
use RemexHtml\HTMLData;
use RemexHtml\Serializer\Serializer;
use RemexHtml\Tokenizer\Tokenizer;
use RemexHtml\TreeBuilder\Dispatcher;
use RemexHtml\TreeBuilder\TreeBuilder;

class ParameterReplacer {
	public const SERVICE_NAME = 'HTMLTemplates:ParameterReplacer';

	/**
	 * Replace {{{1}}} parameters in a fragment of html with context sensitive escaping
	 *
	 * @param string $htmlFragment Fragment
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML with args replaced
	 */
	public function replace( $htmlFragment, Parser $parser, PPFrame $frame ) {
		$options = [];
		$formatter = new ParameterReplacerFormatter( $options, $parser, $frame );
		$serializer = new Serializer( $formatter );
		$treeBuilder = new TreeBuilder( $serializer );
		$dispatcher = new Dispatcher( $treeBuilder );
		$tokenizer = new Tokenizer( $dispatcher, $htmlFragment );

		$tokenizer->execute( [
			'fragmentNamespace' => HTMLData::NS_HTML,
			'fragmentName' => 'div',
		] );

		return $serializer->getResult();
	}
}
