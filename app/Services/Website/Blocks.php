<?php

namespace App\Services\Website;

use App\Models\Website\Form;

/**
 * The building blocks of website pages. The editor in the portal is drawn
 * from SCHEMA, and everything saved is cleaned against it, so a page can only
 * hold known blocks with known fields (no raw HTML).
 *
 * Field types: text, textarea, markdown, url, image, select, toggle, list (of sub-fields).
 */
class Blocks
{
    protected const THEMES = ['light' => 'Light', 'grey' => 'Grey', 'dark' => 'Dark', 'blue' => 'Blue glow'];

    public static function schema(): array
    {
        return [
            'hero' => ['label' => 'Hero', 'icon' => 'ri-layout-top-line', 'hint' => 'A big headline at the top of a page.', 'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small line above'],
                'title' => ['type' => 'text', 'label' => 'Headline'],
                'rotating' => ['type' => 'text', 'label' => 'Rotating words after the headline (optional, comma separated)'],
                'subtitle' => ['type' => 'textarea', 'label' => 'Sub-headline'],
                'primary_label' => ['type' => 'text', 'label' => 'Main button'],
                'primary_url' => ['type' => 'url', 'label' => 'Main button link'],
                'secondary_label' => ['type' => 'text', 'label' => 'Second link'],
                'secondary_url' => ['type' => 'url', 'label' => 'Second link goes to'],
                'image' => ['type' => 'image', 'label' => 'Picture (optional)'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
            ]],
            'tiles' => ['label' => 'Tiles', 'icon' => 'ri-layout-grid-line', 'hint' => 'Big two-up tiles, like apple.com. Fill them yourself or from your products, works or courses.', 'fields' => [
                'source' => ['type' => 'select', 'label' => 'Fill from', 'options' => ['manual' => 'The tiles below', 'products' => 'Featured products', 'works' => 'Featured works', 'courses' => 'Open courses']],
                'items' => ['type' => 'list', 'label' => 'Tiles', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'subtitle' => ['type' => 'text', 'label' => 'Line under it'],
                    'link_label' => ['type' => 'text', 'label' => 'Link text'],
                    'url' => ['type' => 'url', 'label' => 'Link'],
                    'image' => ['type' => 'image', 'label' => 'Picture'],
                    'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
                ]],
            ]],
            'features' => ['label' => 'Features', 'icon' => 'ri-apps-line', 'hint' => 'A grid of short points with icons.', 'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small line above'],
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
                'items' => ['type' => 'list', 'label' => 'Points', 'fields' => [
                    'icon' => ['type' => 'text', 'label' => 'Icon (Remix name, e.g. ri-code-s-slash-line)'],
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ]],
            ]],
            'split' => ['label' => 'Picture and text', 'icon' => 'ri-layout-column-line', 'hint' => 'Text beside a picture.', 'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small line above'],
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'body' => ['type' => 'markdown', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Picture'],
                'image_side' => ['type' => 'select', 'label' => 'Picture on the', 'options' => ['right' => 'Right', 'left' => 'Left']],
                'link_label' => ['type' => 'text', 'label' => 'Link text'],
                'url' => ['type' => 'url', 'label' => 'Link'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
            ]],
            'stats' => ['label' => 'Numbers', 'icon' => 'ri-bar-chart-2-line', 'hint' => 'A row of big numbers.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
                'items' => ['type' => 'list', 'label' => 'Numbers', 'fields' => [
                    'value' => ['type' => 'text', 'label' => 'Number'],
                    'label' => ['type' => 'text', 'label' => 'What it counts'],
                ]],
            ]],
            'text' => ['label' => 'Text', 'icon' => 'ri-text', 'hint' => 'A heading and paragraphs.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'body' => ['type' => 'markdown', 'label' => 'Text'],
                'align' => ['type' => 'select', 'label' => 'Align', 'options' => ['left' => 'Left', 'center' => 'Centre']],
            ]],
            'products' => ['label' => 'Products', 'icon' => 'ri-stack-line', 'hint' => 'Your products, from Website → Products.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'featured_only' => ['type' => 'toggle', 'label' => 'Featured products only'],
            ]],
            'works' => ['label' => 'Our work', 'icon' => 'ri-briefcase-4-line', 'hint' => 'Case studies, from Website → Works.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'featured_only' => ['type' => 'toggle', 'label' => 'Featured works only'],
            ]],
            'courses' => ['label' => 'Courses', 'icon' => 'ri-book-open-line', 'hint' => 'Published Academy courses taking registrations.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
            ]],
            'steps' => ['label' => 'Steps', 'icon' => 'ri-route-line', 'hint' => 'How you work, as numbered steps (01, 02, 03…).', 'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small line above'],
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
                'items' => ['type' => 'list', 'label' => 'Steps', 'fields' => [
                    'icon' => ['type' => 'text', 'label' => 'Icon (Remix name)'],
                    'title' => ['type' => 'text', 'label' => 'Title'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                ]],
            ]],
            'testimonials' => ['label' => 'Quotes', 'icon' => 'ri-double-quotes-l', 'hint' => 'What clients and students say.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'items' => ['type' => 'list', 'label' => 'Quotes', 'fields' => [
                    'quote' => ['type' => 'textarea', 'label' => 'Quote'],
                    'name' => ['type' => 'text', 'label' => 'Name'],
                    'role' => ['type' => 'text', 'label' => 'Role and company'],
                ]],
            ]],
            'logos' => ['label' => 'Logos', 'icon' => 'ri-shield-star-line', 'hint' => 'Clients or partners.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'scroll' => ['type' => 'toggle', 'label' => 'Scroll them past in a moving strip'],
                'items' => ['type' => 'list', 'label' => 'Logos', 'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Name'],
                    'image' => ['type' => 'image', 'label' => 'Logo'],
                ]],
            ]],
            'cta' => ['label' => 'Call to action', 'icon' => 'ri-cursor-line', 'hint' => 'A closing line with a button.', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Headline'],
                'text' => ['type' => 'textarea', 'label' => 'Text'],
                'button_label' => ['type' => 'text', 'label' => 'Button'],
                'url' => ['type' => 'url', 'label' => 'Button link'],
                'theme' => ['type' => 'select', 'label' => 'Background', 'options' => self::THEMES],
            ]],
            'form' => ['label' => 'Form', 'icon' => 'ri-survey-line', 'hint' => 'One of your forms, from Website → Forms.', 'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'form' => ['type' => 'select', 'label' => 'Form', 'options' => 'forms'],
            ]],
        ];
    }

    /** The schema with dynamic options (forms) filled in, for the editor. */
    public static function forEditor(): array
    {
        $forms = Form::orderBy('name')->pluck('name', 'slug')->all();
        $schema = self::schema();
        foreach ($schema as &$block) {
            foreach ($block['fields'] as &$f) {
                if (($f['options'] ?? null) === 'forms') $f['options'] = $forms;
            }
        }

        return $schema;
    }

    /** Keep only known blocks and fields, trimmed to sensible lengths. */
    public static function clean(mixed $blocks): array
    {
        if (is_string($blocks)) $blocks = json_decode($blocks, true);
        if (!is_array($blocks)) return [];
        $schema = self::schema();
        $out = [];
        foreach (array_slice(array_values($blocks), 0, 40) as $block) {
            $type = $block['type'] ?? null;
            if (!is_array($block) || !isset($schema[$type])) continue;
            $out[] = ['type' => $type] + self::fields($schema[$type]['fields'], $block);
        }

        return $out;
    }

    protected static function fields(array $fields, array $data): array
    {
        $clean = [];
        foreach ($fields as $name => $f) {
            $v = $data[$name] ?? null;
            $clean[$name] = match ($f['type']) {
                'toggle' => (bool) $v,
                'list' => array_values(array_map(fn ($item) => self::fields($f['fields'], is_array($item) ? $item : []),
                    array_slice(is_array($v) ? array_values($v) : [], 0, 24))),
                'select' => self::select($f, $v),
                'url', 'image' => self::link($v),
                'markdown' => mb_substr(trim((string) $v), 0, 10000),
                'textarea' => mb_substr(trim((string) $v), 0, 1000),
                default => mb_substr(trim((string) $v), 0, 200),
            };
        }

        return $clean;
    }

    protected static function select(array $f, mixed $v): string
    {
        $v = (string) $v;
        if (($f['options'] ?? null) === 'forms') return preg_match('/^[a-z0-9-]{1,120}$/', $v) ? $v : '';

        return array_key_exists($v, $f['options']) ? $v : (string) array_key_first($f['options']);
    }

    /** Links and pictures: web addresses, site paths, uploaded files, e-mail and phone links. Nothing else (no javascript:). */
    public static function link(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '') return '';
        if (preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $v) || preg_match('#^website/[A-Za-z0-9._/-]+$#', $v)) {
            return mb_substr($v, 0, 500);
        }

        return '';
    }
}
