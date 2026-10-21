<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The school's choice of AI for learning & career guidance (single row).
 */
class AiGuidanceSetting extends Model
{
    protected $table = 'ai_guidance_settings';

    protected $fillable = [
        'enabled', 'provider', 'model', 'api_key', 'for_parents', 'for_students',
        'refreshes_per_term', 'school_context', 'updated_by',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'enabled'      => 'boolean',
        'for_parents'  => 'boolean',
        'for_students' => 'boolean',
        'api_key'      => 'encrypted',
    ];

    public const PROVIDERS = [
        'rules'     => ['Built-in (no external AI)', null],
        'anthropic' => ['Anthropic Claude', 'claude-sonnet-5-5'],
        'openai'    => ['OpenAI', 'gpt-4o-mini'],
        'gemini'    => ['Google Gemini', 'gemini-2.0-flash'],
    ];

    public static function current(): self
    {
        if (!Schema::hasTable('ai_guidance_settings')) {
            return new self(['enabled' => true, 'provider' => 'rules', 'for_parents' => true, 'for_students' => true, 'refreshes_per_term' => 3]);
        }
        return self::find(1) ?? self::create(['id' => 1, 'enabled' => true, 'provider' => 'rules']);
    }

    public function usesAi(): bool
    {
        return $this->provider !== 'rules' && !empty($this->api_key);
    }

    public function modelName(): ?string
    {
        return $this->model ?: (self::PROVIDERS[$this->provider][1] ?? null);
    }
}
