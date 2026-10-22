<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Companies, contacts, the deal pipeline and the activity timeline.
 *
 * Deals move through the open stages on the pipeline board, then are
 * marked won or lost (lost needs a reason). Winning a deal makes its
 * company a client. Every stage change is written to the timeline.
 */
class CrmService
{
    // ── Companies and contacts ──────────────────────────────────────────

    public function saveCompany(array $data, User $by, ?Company $company = null): Company
    {
        $data['website'] = $this->url($data['website'] ?? null);
        if ($company) {
            $company->update($data);

            return $company;
        }

        return Company::create($data + ['created_by' => $by->id, 'owner_id' => $data['owner_id'] ?? $by->id]);
    }

    public function saveContact(array $data, ?Contact $contact = null): Contact
    {
        return DB::transaction(function () use ($data, $contact) {
            $data['email'] = isset($data['email']) ? (Str::lower(trim($data['email'])) ?: null) : null;
            $contact ? $contact->update($data) : $contact = Contact::create($data);
            if ($contact->is_primary && $contact->company_id) {
                Contact::where('company_id', $contact->company_id)->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            return $contact;
        });
    }

    /**
     * Give a contact a client portal account (user type client, role Client).
     *
     * @return array{0: User, 1: ?string} the account, and the temporary password when one was made
     */
    public function giveAccess(Contact $contact): array
    {
        $this->check($contact->user_id !== null, 'email', 'This contact already has portal access.');
        $this->check(!$contact->email, 'email', 'Add an email address for this contact first.');

        if ($existing = User::where('email', $contact->email)->first()) {
            $this->check(!$existing->isClient(), 'email', 'That email belongs to a staff or student account. Use a different email for the client.');
            $this->check(Contact::where('user_id', $existing->id)->exists(), 'email', 'That account is already linked to another contact.');
            $contact->update(['user_id' => $existing->id]);

            return [$existing, null];
        }

        $password = Str::password(10, symbols: false);
        $user = DB::transaction(function () use ($contact, $password) {
            $user = User::create([
                'name' => $contact->name, 'email' => $contact->email, 'phone_number' => $contact->phone,
                'user_type' => User::TYPE_CLIENT, 'password' => $password,
            ]);
            $user->forceFill(['must_change_password' => true])->save();
            $user->assignRole('Client');
            $contact->update(['user_id' => $user->id]);

            return $user;
        });

        return [$user, $password];
    }

    // ── Deals ───────────────────────────────────────────────────────────

    public function saveDeal(array $data, User $by, ?Deal $deal = null): Deal
    {
        $this->checkContact($data);
        if (isset($data['value'])) $data['value'] = Money::major(Money::minor($data['value']));

        if ($deal) {
            $deal->update($data);

            return $deal;
        }

        $stage = in_array($data['stage'] ?? 'new', Deal::OPEN, true) ? ($data['stage'] ?? 'new') : 'new';

        return Deal::create($data + [
            'stage'       => $stage,
            'probability' => $data['probability'] ?? Deal::STAGES[$stage][1],
            'currency'    => strtoupper(config('qudroid.academy.currency', 'NGN')),
            'owner_id'    => $data['owner_id'] ?? $by->id,
            'created_by'  => $by->id,
            'position'    => (int) Deal::where('stage', $stage)->max('position') + 1,
        ]);
    }

    /** @param int[] $order the column's deal ids in their new order */
    public function move(Deal $deal, string $stage, array $order, User $by): Deal
    {
        $this->check(!in_array($stage, Deal::OPEN, true), 'stage', 'Mark a deal won or lost from the deal page.');
        $this->check(!$deal->isOpen(), 'stage', 'Reopen this deal before moving it.');

        DB::transaction(function () use ($deal, $stage, $order, $by) {
            if ($deal->stage !== $stage) {
                $this->stage($deal, $stage, $by);
            }
            $ids = Deal::whereIn('id', $order)->where('stage', $stage)->pluck('id')->all();
            foreach (array_values(array_filter($order, fn ($id) => in_array((int) $id, $ids, true))) as $i => $id) {
                Deal::whereKey($id)->update(['position' => $i + 1]);
            }
        });

        return $deal->fresh();
    }

    public function win(Deal $deal, User $by): Deal
    {
        $this->check(!$deal->isOpen(), 'stage', 'This deal is already closed.');
        DB::transaction(function () use ($deal, $by) {
            $this->stage($deal, 'won', $by);
            $deal->update(['won_at' => now(), 'lost_at' => null, 'lost_reason' => null]);
            $deal->company?->update(['status' => 'client']);
        });
        if ($deal->owner_id && $deal->owner_id !== $by->id) {
            PortalNotifier::toUsers([$deal->owner_id], 'Deal won: ' . $deal->title, $by->name . ' marked it won.', route('crm.deals.show', $deal), 'crm');
        }

        return $deal;
    }

    public function lose(Deal $deal, User $by, string $reason): Deal
    {
        $this->check(!$deal->isOpen(), 'stage', 'This deal is already closed.');
        $this->check(trim($reason) === '', 'reason', 'Say why it was lost.');
        DB::transaction(function () use ($deal, $by, $reason) {
            $this->stage($deal, 'lost', $by, trim($reason));
            $deal->update(['lost_at' => now(), 'won_at' => null, 'lost_reason' => mb_substr(trim($reason), 0, 255)]);
        });

        return $deal;
    }

    public function reopen(Deal $deal, User $by): Deal
    {
        $this->check($deal->isOpen(), 'stage', 'This deal is open.');
        $this->stage($deal, 'negotiation', $by);
        $deal->update(['won_at' => null, 'lost_at' => null, 'lost_reason' => null]);

        return $deal;
    }

    // ── Timeline ────────────────────────────────────────────────────────

    public function log(Model $subject, array $data, User $by): Activity
    {
        $type = $data['type'] ?? 'note';
        $this->check(!in_array($type, Activity::LOGGABLE, true), 'type', 'Unknown activity type.');
        $this->check(trim((string) ($data['body'] ?? '')) === '', 'body', 'Write what happened or what to do.');
        $followUp = $type === 'follow_up';
        $this->check($followUp && empty($data['due_at']), 'due_at', 'Give the follow-up a date.');

        $activity = Activity::create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'company_id'   => $subject instanceof Company ? $subject->id : $subject->company_id,
            'type'         => $type,
            'body'         => trim($data['body']),
            'due_at'       => $followUp ? Carbon::parse($data['due_at']) : null,
            'assigned_to'  => $followUp ? ((int) ($data['assigned_to'] ?? 0) ?: $by->id) : null,
            'user_id'      => $by->id,
        ]);

        if ($followUp && $activity->assigned_to !== $by->id) {
            PortalNotifier::toUsers([$activity->assigned_to], 'Follow-up for you', $by->name . ': ' . Str::limit($activity->body, 120) . ' (due ' . $activity->due_at->format('j M') . ')',
                $this->link($activity), 'crm');
        }

        return $activity;
    }

    public function complete(Activity $activity): Activity
    {
        $activity->update(['done_at' => $activity->done_at ? null : now()]);

        return $activity;
    }

    public function link(Activity $activity): string
    {
        return match ($activity->subject_type) {
            (new Deal)->getMorphClass()    => route('crm.deals.show', $activity->subject_id),
            (new Contact)->getMorphClass() => route('crm.contacts.show', $activity->subject_id),
            default                        => route('crm.companies.show', $activity->subject_id),
        };
    }

    // ── Numbers ─────────────────────────────────────────────────────────

    /** Pipeline totals in minor units. */
    public function stats(): array
    {
        $open = Deal::open()->get(['value', 'probability']);
        $closed = Deal::whereIn('stage', ['won', 'lost'])->where(fn ($q) => $q->where('won_at', '>=', now()->subDays(90))->orWhere('lost_at', '>=', now()->subDays(90)))->pluck('stage');

        return [
            'openCount' => $open->count(),
            'openValue' => $open->sum(fn ($d) => Money::minor($d->value)),
            'weighted'  => $open->sum(fn ($d) => intdiv(Money::minor($d->value) * $d->probability, 100)),
            'wonMonth'  => Deal::where('stage', 'won')->where('won_at', '>=', now()->startOfMonth())->get(['value'])->sum(fn ($d) => Money::minor($d->value)),
            'winRate'   => $closed->count() ? (int) round($closed->filter(fn ($s) => $s === 'won')->count() * 100 / $closed->count()) : null,
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    protected function stage(Deal $deal, string $to, User $by, ?string $why = null): void
    {
        $from = $deal->stage;
        $deal->stage = $to;
        $deal->probability = Deal::STAGES[$to][1];
        if ($to !== $from) {
            $deal->position = (int) Deal::where('stage', $to)->max('position') + 1;
        }
        $deal->save();

        Activity::create([
            'subject_type' => $deal->getMorphClass(), 'subject_id' => $deal->id, 'company_id' => $deal->company_id,
            'type' => 'stage', 'user_id' => $by->id,
            'body' => (Deal::STAGES[$from][0] ?? $from) . ' → ' . Deal::STAGES[$to][0] . ($why ? ': ' . $why : ''),
        ]);
    }

    protected function checkContact(array $data): void
    {
        if (!empty($data['contact_id']) && !empty($data['company_id'])) {
            $this->check(!Contact::whereKey($data['contact_id'])->where('company_id', $data['company_id'])->exists(), 'contact_id', 'That contact does not work at this company.');
        }
    }

    protected function url(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;

        return preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
