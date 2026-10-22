<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Asset;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The asset register: equipment Qudroid owns, tagged QUD-AST-0001 …,
 * with who has each item. Every hand-over, repair and retirement is
 * kept as an event on the asset.
 */
class AssetService
{
    public function create(array $data, User $by): Asset
    {
        return DB::transaction(function () use ($data, $by) {
            $asset = Asset::create($this->clean($data) + ['tag' => $this->nextTag(), 'status' => 'in_store']);
            $this->event($asset, 'added', $by, null, $asset->purchase_cost ? 'Cost ' . Money::format($asset->purchase_cost) : null);

            return $asset;
        });
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($this->clean($data));

        return $asset;
    }

    public function assign(Asset $asset, User $to, User $by, ?string $note = null): Asset
    {
        $this->check($asset->status !== 'in_store', 'user_id', 'Only an asset in store can be given out. Return it first.');
        $this->check(!$to->isStaff(), 'user_id', 'Assets can only be given to staff.');

        DB::transaction(function () use ($asset, $to, $by, $note) {
            $asset->update(['status' => 'assigned', 'assigned_to' => $to->id, 'assigned_at' => now()]);
            $this->event($asset, 'assigned', $by, $to, $note);
        });
        PortalNotifier::toUsers([$to->id], 'Asset assigned to you: ' . $asset->name,
            $asset->tag . ($asset->serial_number ? ', serial ' . $asset->serial_number : '') . '. You are responsible for it until it is returned.',
            route('me.assets'), 'inventory');

        return $asset;
    }

    public function return(Asset $asset, User $by, ?string $condition = null, ?string $note = null): Asset
    {
        $this->check($asset->status !== 'assigned', 'status', 'This asset isn\'t assigned to anyone.');
        DB::transaction(function () use ($asset, $by, $condition, $note) {
            $from = $asset->holder;
            $asset->update(['status' => 'in_store', 'assigned_to' => null, 'assigned_at' => null]
                + ($condition && isset(Asset::CONDITIONS[$condition]) ? ['condition' => $condition] : []));
            $this->event($asset, 'returned', $by, $from, $note);
        });

        return $asset;
    }

    public function repair(Asset $asset, User $by, ?string $note = null): Asset
    {
        $this->check(!in_array($asset->status, ['in_store', 'assigned'], true), 'status', 'This asset can\'t be sent for repair now.');
        if ($asset->status === 'assigned') $this->return($asset, $by, null, 'Taken in for repair');
        $asset->update(['status' => 'repair']);
        $this->event($asset, 'repair', $by, null, $note);

        return $asset;
    }

    public function repaired(Asset $asset, User $by, ?string $note = null): Asset
    {
        $this->check($asset->status !== 'repair', 'status', 'This asset isn\'t being repaired.');
        $asset->update(['status' => 'in_store']);
        $this->event($asset, 'repaired', $by, null, $note);

        return $asset;
    }

    /** Take an asset out of use for good (sold, scrapped, written off) or record it lost. */
    public function retire(Asset $asset, User $by, string $reason, bool $lost = false): Asset
    {
        $this->check(trim($reason) === '', 'note', 'Say what happened to it.');
        $this->check(in_array($asset->status, ['retired', 'lost'], true), 'status', 'This asset is already off the register.');
        DB::transaction(function () use ($asset, $by, $reason, $lost) {
            $holder = $asset->holder;
            $asset->update(['status' => $lost ? 'lost' : 'retired', 'assigned_to' => null, 'assigned_at' => null]);
            $this->event($asset, $lost ? 'lost' : 'retired', $by, $holder, $reason);
        });

        return $asset;
    }

    public function note(Asset $asset, User $by, string $note): Asset
    {
        $this->check(trim($note) === '', 'note', 'Write a note.');
        $this->event($asset, 'note', $by, null, $note);

        return $asset;
    }

    protected function event(Asset $asset, string $type, User $by, ?User $user = null, ?string $note = null): void
    {
        $asset->events()->create(['type' => $type, 'user_id' => $user?->id, 'created_by' => $by->id,
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null]);
    }

    protected function clean(array $data): array
    {
        if (array_key_exists('purchase_cost', $data)) {
            $data['purchase_cost'] = ($data['purchase_cost'] ?? '') === '' ? null : Money::major(max(0, Money::minor($data['purchase_cost'])));
        }

        return collect($data)->only(['name', 'category_id', 'make_model', 'serial_number', 'supplier_id', 'purchase_date', 'purchase_cost',
            'warranty_until', 'condition', 'location_id', 'notes'])->all();
    }

    protected function nextTag(): string
    {
        $row = DB::table('finance_sequences')->where('key', 'asset')->where('year', 0)->lockForUpdate()->first();
        $n = max((int) ($row->last ?? 0), (int) Asset::count()) + 1;
        $row ? DB::table('finance_sequences')->where('key', 'asset')->where('year', 0)->update(['last' => $n])
             : DB::table('finance_sequences')->insert(['key' => 'asset', 'year' => 0, 'last' => $n]);

        return config('qudroid.finance.number_prefix', 'QUD') . '-AST-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
