<?php

namespace App\Models\Website;

use App\Models\Academy\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's page on the website: photo, headline, bio, skills, links and
 * projects. Public only once the student has agreed and staff have published it.
 */
class StudentProfile extends Model
{
    protected $table = 'site_student_profiles';

    public const STATUSES = ['draft' => 'Not shared', 'pending' => 'Waiting for approval', 'published' => 'On the website', 'hidden' => 'Hidden by staff'];

    /** link key => [label, icon] */
    public const LINKS = [
        'portfolio' => ['Portfolio', 'ri-global-line'],
        'github'    => ['GitHub', 'ri-github-fill'],
        'linkedin'  => ['LinkedIn', 'ri-linkedin-box-fill'],
        'x'         => ['X', 'ri-twitter-x-line'],
        'behance'   => ['Behance', 'ri-behance-fill'],
    ];

    protected $fillable = ['student_id', 'slug', 'headline', 'bio', 'photo', 'location', 'skills', 'links', 'open_to_work', 'status', 'featured',
        'position', 'consented_at', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['skills' => 'array', 'links' => 'array', 'open_to_work' => 'boolean', 'featured' => 'boolean', 'position' => 'integer',
        'consented_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(StudentProject::class, 'profile_id')->orderBy('position')->orderBy('id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('consented_at');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderByDesc('featured')->orderBy('position')->orderByDesc('id');
    }

    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->consented_at !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function name(): string
    {
        return (string) $this->student?->user?->name;
    }

    /** Their uploaded picture, else their portal photo, else null (the page draws initials). */
    public function photoUrl(): ?string
    {
        return Product::url($this->photo) ?? $this->student?->user?->avatar_path_url;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name())))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    /** Links that are filled in, as [key, label, icon, url]. */
    public function linkList(): array
    {
        $out = [];
        foreach (self::LINKS as $k => [$label, $icon]) {
            if (!empty($this->links[$k])) $out[] = [$k, $label, $icon, $this->links[$k]];
        }

        return $out;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
