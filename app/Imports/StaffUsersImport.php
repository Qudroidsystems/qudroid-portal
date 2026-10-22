<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StaffUsersImport implements
    ToModel,
    WithStartRow,
    WithValidation,
    SkipsOnFailure,
    SkipsOnError
{
    use Importable, SkipsFailures, SkipsErrors;

    protected int $rowCounter = 0;
    protected array $created = [];
    protected array $skipped = [];

    public function model(array $row)
    {
        $this->rowCounter++;

        $name     = trim((string) ($row[0] ?? ''));
        $email    = strtolower(trim((string) ($row[1] ?? '')));
        $role     = trim((string) ($row[2] ?? ''));
        $password = trim((string) ($row[3] ?? ''));

        // Skip completely empty rows
        if ($name === '' && $email === '' && $password === '') {
            return null;
        }

        if ($name === '' || $email === '' || $password === '') {
            $this->skipped[] = "Row " . ($this->rowCounter + 1) . ": Name, Email and Password are required.";
            return null;
        }

        // Accept only the exact role "Staff" (case-insensitive)
        if ($role !== '' && strcasecmp($role, 'Staff') !== 0) {
            $this->skipped[] = "Row " . ($this->rowCounter + 1) . ": Role must be 'Staff' (got '{$role}').";
            return null;
        }

        if (User::where('email', $email)->exists()) {
            $this->skipped[] = "Row " . ($this->rowCounter + 1) . ": Email {$email} already exists.";
            return null;
        }

        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'user_type' => User::TYPE_STAFF,
            'password'  => Hash::make($password),
        ]);
        // The password in the sheet is temporary: they choose their own at first sign-in.
        $user->forceFill(['must_change_password' => true])->save();

        // Force the literal role "Staff"
        $user->syncRoles(['Staff']);

        $this->created[] = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ];

        Log::info('Staff user imported', [
            'email'   => $email,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    public function rules(): array
    {
        return [
            '0' => 'nullable|string|max:255',
            '1' => 'nullable|email|max:255',
            '3' => 'nullable|string|min:6|max:100',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '1.email' => 'Email must be a valid email address.',
            '3.min'   => 'Password must be at least 6 characters.',
        ];
    }

    public function startRow(): int
    {
        return 2; // skip header
    }

    public function getCreated(): array
    {
        return $this->created;
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }
}