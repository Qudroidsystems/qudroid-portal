<?php

namespace App\Services\Mail;

/**
 * Two kinds of e-mail HTML clean-up.
 *
 * clean(): what we send. Only simple formatting survives (what the compose box can make).
 * display(): what we receive. Layout and styles stay, but anything that runs code, loads
 * other pages or submits forms goes. It is shown in a sandboxed frame on top of this, whose
 * Content-Security-Policy decides whether remote images (and remote CSS) load.
 */
class HtmlSanitizer
{
    protected const COMPOSE_TAGS = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'blockquote', 'div', 'span',
        'h1', 'h2', 'h3', 'hr', 'img', 'pre', 'code', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'font', 'sub', 'sup'];

    protected const COMPOSE_ATTRS = ['href', 'src', 'alt', 'title', 'style', 'colspan', 'rowspan', 'width', 'height', 'color', 'align', 'dir'];

    protected const DROP_WITH_CONTENT = ['script', 'iframe', 'object', 'embed', 'applet', 'frame', 'frameset', 'noscript', 'template', 'svg', 'math', 'video', 'audio', 'canvas'];

    public static function clean(string $html): string
    {
        return self::walk($html, function (\DOMElement $el) {
            $tag = strtolower($el->tagName);
            if (!in_array($tag, self::COMPOSE_TAGS, true)) return 'unwrap';
            foreach (iterator_to_array($el->attributes) as $a) {
                if (!in_array(strtolower($a->name), self::COMPOSE_ATTRS, true)) $el->removeAttribute($a->name);
            }

            return 'keep';
        });
    }

    public static function display(string $html): string
    {
        return self::walk($html, function (\DOMElement $el) {
            $tag = strtolower($el->tagName);
            if (in_array($tag, ['form', 'html', 'body'], true)) return 'unwrap';
            if (in_array($tag, ['input', 'button', 'select', 'textarea', 'base', 'link', 'meta'], true)) return 'drop';

            return 'keep';
        }, true);
    }

    protected static function walk(string $html, callable $decide, bool $keepStyleTags = false): string
    {
        if (trim($html) === '') return '';
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('__root') ?? $doc->documentElement;
        if (!$root) return '';

        $walk = function (\DOMNode $node) use (&$walk, $decide, $keepStyleTags) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    $node->removeChild($child);
                    continue;
                }
                if (!$child instanceof \DOMElement) continue;
                $tag = strtolower($child->tagName);
                if ($tag === 'head' && $keepStyleTags) {
                    // Keep the e-mail's stylesheets; drop the rest of <head>.
                    foreach (iterator_to_array($child->getElementsByTagName('style')) as $st) $node->insertBefore($st, $child);
                }
                if (in_array($tag, self::DROP_WITH_CONTENT, true) || ($tag === 'style' && !$keepStyleTags) || in_array($tag, ['head', 'title'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                // Never anything that runs code.
                foreach (iterator_to_array($child->attributes) as $a) {
                    $name = strtolower($a->name);
                    $value = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', html_entity_decode($a->value)));
                    if (str_starts_with($name, 'on') || $name === 'srcdoc' || $name === 'formaction'
                        || (in_array($name, ['href', 'src', 'action', 'background', 'xlink:href', 'poster'], true) && preg_match('/^(javascript|vbscript|data:text\/html)/', $value))) {
                        $child->removeAttribute($a->name);
                    }
                    if ($name === 'style' && preg_match('/expression\(|javascript:|behaviou?r:/i', $a->value)) $child->removeAttribute($a->name);
                }
                if ($tag === 'a' && $child->hasAttribute('href')) $child->setAttribute('rel', 'noopener noreferrer');
                $what = $decide($child);
                if ($what === 'drop') {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if ($what === 'unwrap') {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);

        return $out;
    }
}
