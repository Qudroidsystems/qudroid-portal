<?php
// Split into separate files on commit: see markers "=== FILE: path ===".

// === FILE: app/Models/MessagingSetting.php ===
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Provider settings per channel (sms / whatsapp / email), entered by an admin
 * in Notices › Settings. Secret values are encrypted with the app key.
 */
class MessagingSetting extends Model
{
    protected $table = 'messaging_settings';
    protected $fillable = ['channel', 'driver', 'is_active', 'config', 'updated_by'];
    protected $hidden = ['config'];
    protected $casts = ['is_active' => 'boolean', 'config' => 'array'];

    protected const ENC = 'enc:';

    /** Field definitions per channel/driver; 'secret' fields are encrypted. */
    public const DRIVERS = [
        'sms' => [
            'log'    => ['label' => 'Log only (testing — nothing is sent)', 'fields' => []],
            'termii' => ['label' => 'Termii', 'fields' => [
                'api_key'   => ['label' => 'API key', 'secret' => true, 'required' => true],
                'sender_id' => ['label' => 'Sender ID (approved by Termii)', 'secret' => false, 'required' => true, 'max' => 11],
                'route'     => ['label' => 'Route', 'secret' => false, 'required' => true, 'options' => ['generic' => 'Generic', 'dnd' => 'DND (delivers to DND numbers; sender ID must be approved for DND)']],
                'base_url'  => ['label' => 'API base URL', 'secret' => false, 'required' => false, 'default' => 'https://api.ng.termii.com'],
            ]],
        ],
        'whatsapp' => [
            'log'  => ['label' => 'Log only (testing — nothing is sent)', 'fields' => []],
            'meta' => ['label' => 'WhatsApp Cloud API (Meta)', 'fields' => [
                'access_token'    => ['label' => 'Permanent access token', 'secret' => true, 'required' => true],
                'phone_number_id' => ['label' => 'Phone number ID', 'secret' => false, 'required' => true],
                'template_name'   => ['label' => 'Approved template name', 'secret' => false, 'required' => true, 'default' => 'school_notice'],
                'template_lang'   => ['label' => 'Template language code', 'secret' => false, 'required' => true, 'default' => 'en'],
                'api_version'     => ['label' => 'Graph API version', 'secret' => false, 'required' => true, 'default' => 'v21.0'],
            ]],
        ],
        'email' => [
            'mail' => ['label' => 'School mail server (MAIL_* settings)', 'fields' => [
                'from_name'  => ['label' => 'From name', 'secret' => false, 'required' => false],
                'reply_to'   => ['label' => 'Reply-to address', 'secret' => false, 'required' => false],
            ]],
        ],
    ];

    public static function for(string $channel): self
    {
        return static::firstOrCreate(
            ['channel' => $channel],
            ['driver' => $channel === 'email' ? 'mail' : 'log', 'is_active' => $channel === 'email', 'config' => []]
        );
    }

    public function fields(): array
    {
        return self::DRIVERS[$this->channel][$this->driver]['fields'] ?? [];
    }

    public function value(string $field): ?string
    {
        $v = $this->config[$field] ?? null;
        if ($v === null || $v === '') {
            return self::DRIVERS[$this->channel][$this->driver]['fields'][$field]['default'] ?? null;
        }
        if (is_string($v) && str_starts_with($v, self::ENC)) {
            try {
                return Crypt::decryptString(substr($v, strlen(self::ENC)));
            } catch (\Throwable $e) {
                return null;
            }
        }
        return (string) $v;
    }

    public function put(string $field, ?string $value): void
    {
        $config = $this->config ?? [];
        $value  = $value === null ? null : trim($value);
        $secret = $this->fields()[$field]['secret'] ?? false;

        if ($value === null || $value === '') {
            unset($config[$field]);
        } else {
            $config[$field] = $secret ? self::ENC . Crypt::encryptString($value) : $value;
        }
        $this->config = $config;
    }

    public function masked(string $field): ?string
    {
        $v = $this->value($field);
        if ($v === null) return null;
        return ($this->fields()[$field]['secret'] ?? false) ? '••••••' . substr($v, -4) : $v;
    }

    /** Ready to send real messages? ('log' counts as ready — it just logs.) */
    public function isConfigured(): bool
    {
        foreach ($this->fields() as $f => $def) {
            if (!empty($def['required']) && !$this->value($f)) return false;
        }
        return true;
    }

    public function isLive(): bool
    {
        return $this->is_active && $this->driver !== 'log' && $this->isConfigured();
    }
}

// === FILE: app/Models/SchoolNotice.php ===
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolNotice extends Model
{
    protected $table = 'school_notices';

    protected $fillable = [
        'type', 'title', 'message', 'sms_text', 'event_date', 'event_end_date', 'event_time',
        'audience', 'channels', 'reminders', 'status', 'send_at', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'audience'       => 'array',
        'channels'       => 'array',
        'reminders'      => 'array',
        'event_date'     => 'date',
        'event_end_date' => 'date',
        'send_at'        => 'datetime',
        'sent_at'        => 'datetime',
    ];

    public const TYPES = [
        'ca_test'    => ['label' => 'CA test',            'icon' => 'ri-edit-2-line'],
        'exam'       => ['label' => 'Examination',        'icon' => 'ri-file-list-3-line'],
        'mock'       => ['label' => 'Mock examination',   'icon' => 'ri-draft-line'],
        'midterm'    => ['label' => 'Midterm break',      'icon' => 'ri-cup-line'],
        'holiday'    => ['label' => 'Public holiday',     'icon' => 'ri-sun-line'],
        'resumption' => ['label' => 'Resumption',         'icon' => 'ri-school-line'],
        'vacation'   => ['label' => 'End of term / vacation', 'icon' => 'ri-plane-line'],
        'meeting'    => ['label' => 'PTA / meeting',      'icon' => 'ri-group-line'],
        'fees'       => ['label' => 'Fees',               'icon' => 'ri-money-dollar-circle-line'],
        'event'      => ['label' => 'School event',       'icon' => 'ri-calendar-event-line'],
        'general'    => ['label' => 'General notice',     'icon' => 'ri-megaphone-line'],
    ];

    public const STATUS = [
        'draft'     => ['label' => 'Draft',     'pill' => 'st-muted'],
        'scheduled' => ['label' => 'Scheduled', 'pill' => 'st-info'],
        'sending'   => ['label' => 'Sending',   'pill' => 'st-pending'],
        'sent'      => ['label' => 'Sent',      'pill' => 'st-paid'],
        'cancelled' => ['label' => 'Cancelled', 'pill' => 'st-danger'],
    ];

    public function dispatches(): HasMany
    {
        return $this->hasMany(NoticeDispatch::class, 'school_notice_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NoticeDelivery::class, 'school_notice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst($this->type);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}

// === FILE: app/Models/NoticeDispatch.php ===
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoticeDispatch extends Model
{
    protected $table = 'notice_dispatches';

    protected $fillable = [
        'school_notice_id', 'kind', 'label', 'run_at', 'status',
        'total', 'sent', 'failed', 'skipped', 'started_at', 'finished_at', 'error',
    ];

    protected $casts = [
        'run_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(SchoolNotice::class, 'school_notice_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NoticeDelivery::class, 'notice_dispatch_id');
    }
}

// === FILE: app/Models/NoticeDelivery.php ===
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeDelivery extends Model
{
    protected $table = 'notice_deliveries';

    protected $fillable = [
        'notice_dispatch_id', 'school_notice_id', 'channel', 'recipient', 'recipient_name', 'audience_type',
        'student_ids', 'body', 'status', 'error', 'provider_message_id', 'attempts', 'sent_at',
    ];

    protected $casts = ['student_ids' => 'array', 'sent_at' => 'datetime'];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(NoticeDispatch::class, 'notice_dispatch_id');
    }
}
