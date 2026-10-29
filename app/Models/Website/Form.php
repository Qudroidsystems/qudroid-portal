<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A form people fill on the website (contact, enquiries, sign-ups). */
class Form extends Model
{
    public const TYPES = [
        'text' => 'Short text', 'textarea' => 'Long text', 'email' => 'E-mail', 'phone' => 'Phone',
        'number' => 'Number', 'date' => 'Date', 'select' => 'Choose one', 'checkbox' => 'Tick box',
    ];

    protected $table = 'site_forms';

    protected $fillable = ['slug', 'name', 'intro', 'fields', 'button_label', 'success_message', 'notify', 'to_crm', 'status'];

    protected $casts = ['fields' => 'array', 'to_crm' => 'boolean'];

    public function entries(): HasMany
    {
        return $this->hasMany(FormEntry::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The contact form every site starts with. */
    public static function contact(): self
    {
        return static::firstOrCreate(['slug' => 'contact'], [
            'name' => 'Contact us',
            'intro' => 'Tell us about your project or question and we will get back to you within one working day.',
            'fields' => [
                ['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false],
                ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
                ['key' => 'topic', 'label' => 'What is it about?', 'type' => 'select', 'required' => true,
                    'options' => ['A new project', 'Our products', 'Academy and training', 'Partnership', 'Something else']],
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
            ],
            'button_label' => 'Send message',
            'success_message' => 'Thank you. We have your message and will reply soon.',
            'to_crm' => true,
            'status' => 'open',
        ]);
    }
}
