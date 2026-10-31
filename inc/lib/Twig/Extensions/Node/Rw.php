<?php

/*
 * This file is part of Tinyboard.
 *
 * Node for the {% rw %} tag: captures the inner body as a single printable
 * expression, applies the "remove_whitespace" filter and prints the result.
 *
 * Implementation note: instead of re-emitting the child node tree (which would
 * require version-specific escaping handling), we compile the body into an
 * output buffer at runtime and filter the captured string. This behaves
 * identically to {% filter remove_whitespace %} for our templates (autoescape
 * is disabled board-wide) and relies only on APIs common to Twig 1.x and 3.x.
 */
class Twig_Extensions_Node_Rw extends Twig_Node
{
    public function __construct(Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(array('body' => $body), array(), $lineno, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write("echo twig_remove_whitespace_filter(ob_get_clean());\n");
    }
}
