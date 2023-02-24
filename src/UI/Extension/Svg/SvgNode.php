<?php

declare(strict_types = 1);

namespace App\UI\Extension\Svg;

use Generator;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

class SvgNode extends StatementNode
{

	public ExpressionNode $svgFileName;

	public ArrayNode $params;

	public static function create(Tag $tag): self
	{
		$node = new self();
		$node->svgFileName = $tag->parser->parseUnquotedStringOrExpression();
		$tag->parser->stream->tryConsume(',');
		$node->params = $tag->parser->parseArguments();

		return $node;
	}

	public function print(PrintContext $context): string
	{
		$condition = $context->format(
			"is_readable(\$this->global->svgDir . '/' . %node) && is_file(\$this->global->svgDir . '/' . %node)",
			$this->svgFileName,
			$this->svgFileName,
		);

		$printSvg = count($this->params->items) === 0
			? $context->format("echo file_get_contents(\$this->global->svgDir . '/' . %node);", $this->svgFileName)
			: $context->format('
					if (is_array(%node) && count(%node) > 0) {
						$svg = file_get_contents($this->global->svgDir . \'/\' . %node);
						foreach (%args as $key => $value) {
							$attr = $key . "=\"" . $value . "\""; 
							$svg = str_replace(\'<svg\', \'<svg \' . $attr, $svg);   
						}
						
						echo $svg;
					}
				', $this->params, $this->params, $this->svgFileName, $this->params);

		$msg = $context->format('"Given svg file not found or unreadable (%node)."', $this->svgFileName);
		$error = 'throw new InvalidArgumentException(' . $msg . ');';

		return $context->format(
			'if (' . $condition . ') {' . $printSvg . ' } else { ' . $error . '}',
		);
	}

	public function &getIterator(): Generator
	{
		if (false) {
			yield;
		}
	}

}
