<?php

namespace App\Services\Website;

use App\Models\Academy\Course;
use App\Models\Website\Form;
use App\Models\Website\Product;
use App\Models\Website\Work;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** What the website's blocks and pages read: published content, Markdown, and image addresses. */
class Content
{
    public static function products(bool $featuredOnly = false): Collection
    {
        return Product::published()->when($featuredOnly, fn ($q) => $q->where('featured', true))->ordered()->get();
    }

    public static function works(bool $featuredOnly = false): Collection
    {
        return Work::published()->when($featuredOnly, fn ($q) => $q->where('featured', true))->ordered()->get();
    }

    /** Published courses, open ones first, each with its cohorts that are taking registrations. */
    public static function courses(): Collection
    {
        return Course::published()
            ->with(['cohorts' => fn ($q) => $q->acceptingApplications()->orderBy('starts_on')])
            ->orderBy('title')->get()
            ->sortByDesc(fn ($c) => $c->cohorts->isNotEmpty())->values();
    }

    /** Tiles for a "tiles" block: typed in, or made from products / works / courses. */
    public static function tiles(array $b): array
    {
        $themes = ['grey', 'dark', 'dark', 'grey'];   // alternate like apple.com

        return match ($b['source'] ?? 'manual') {
            'products' => self::products(true)->values()->map(fn ($p, $i) => [
                'title' => $p->name, 'subtitle' => $p->tagline, 'url' => route('site.products.show', $p), 'link_label' => 'Learn more',
                'image' => $p->imageUrl(), 'icon' => $p->icon ?: 'ri-stack-line', 'color' => $p->color, 'theme' => $themes[$i % 4],
                'secondary' => $p->link_url ? ['label' => $p->link_label ?: 'Get started', 'url' => $p->link_url] : null,
            ])->all(),
            'works' => self::works(true)->values()->map(fn ($w, $i) => [
                'title' => $w->title, 'subtitle' => $w->summary ? Str::limit($w->summary, 90) : $w->client, 'url' => route('site.works.show', $w), 'link_label' => 'See the work',
                'image' => $w->imageUrl(), 'icon' => 'ri-briefcase-4-line', 'color' => $w->color, 'theme' => $themes[$i % 4], 'secondary' => null,
            ])->all(),
            'courses' => self::courses()->filter(fn ($c) => $c->cohorts->isNotEmpty())->values()->map(fn ($c, $i) => [
                'title' => $c->title, 'subtitle' => $c->summary ? Str::limit($c->summary, 90) : $c->level_label, 'url' => route('site.academy.course', $c->slug), 'link_label' => 'Learn more',
                'image' => Product::url($c->image), 'icon' => 'ri-book-open-line', 'color' => '#6366F1', 'theme' => $themes[$i % 4],
                'secondary' => ['label' => 'Register', 'url' => route('site.academy.course', $c->slug) . '#register'],
            ])->all(),
            default => array_map(fn ($t) => $t + ['icon' => null, 'color' => null, 'secondary' => null, 'image' => Product::url($t['image'] ?? null)], $b['items'] ?? []),
        };
    }

    public static function form(?string $slug): ?Form
    {
        if (!$slug) return null;
        if ($slug === 'contact') return Form::contact();

        return Form::where('slug', $slug)->first();
    }

    /** Markdown to safe HTML (raw HTML is stripped, unsafe links dropped). */
    public static function md(?string $text): HtmlString
    {
        return new HtmlString(Str::markdown((string) $text, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    public static function money(int|float|string|null $amount, string $currency = 'NGN'): string
    {
        return Money::format($amount ?? 0, $currency);
    }
}

