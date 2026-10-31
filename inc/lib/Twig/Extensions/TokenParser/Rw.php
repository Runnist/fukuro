<?php

/*
 * This file is part of Tinyboard.
 *
 * Custom block tag {% rw %} ... {% endrw %}, functionally equivalent to
 * {% filter remove_whitespace %} ... {% endfilter %}. Introduced because the
 * "filter" tag is deprecated in Twig 1.x and REMOVED in Twig 3, while this
 * implementation uses only stable public APIs (subparse / new Node_MacroBodyProxy
 * via a raw body capture) that exist in BOTH Twig 1.14 and Twig 3.x.
 */
class Twig_Extensions_TokenParser_Rw extends Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Twig_Token $token A Twig_Token instance
     *
     * @return Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $stream->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideForEnd'), true);
        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new Twig_Extensions_Node_Rw($body, $lineno, $this->getTag());
    }

    public function decideForEnd(Twig_Token $token)
    {
        return $token->test('endrw');
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'rw';
    }
}
