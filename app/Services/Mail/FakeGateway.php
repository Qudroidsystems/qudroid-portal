<?php

namespace App\Services\Mail;

/**
 * An in-memory IMAP server for tests: Mailboxes::fake() swaps it in, and
 * FakeGateway::deliver() drops mail into a mailbox the way a real server would.
 */
class FakeGateway implements Gateway
{
    /** email => path => ['uidvalidity', 'next', 'messages' => uid => [raw, flags]] */
    public static array $boxes = [];
    public static array $log = [];

    public function __construct(protected string $email)
    {
        self::$boxes[$email] ??= self::fresh();
    }

    public static function reset(): void
    {
        self::$boxes = [];
        self::$log = [];
    }

    protected static function fresh(): array
    {
        $box = [];
        foreach (['INBOX' => [], 'Sent' => ['\Sent'], 'Drafts' => ['\Drafts'], 'Trash' => ['\Trash'], 'Junk' => ['\Junk'], 'Archive' => ['\Archive']] as $p => $f) {
            $box[$p] = ['flags' => $f, 'uidvalidity' => 1, 'next' => 1, 'messages' => []];
        }

        return $box;
    }

    public static function deliver(string $email, string $raw, string $path = 'INBOX', array $flags = []): int
    {
        self::$boxes[$email] ??= self::fresh();
        $f = &self::$boxes[$email][$path];
        $uid = $f['next']++;
        $f['messages'][$uid] = ['raw' => $raw, 'flags' => $flags];

        return $uid;
    }

    protected function &box(string $path): array
    {
        if (!isset(self::$boxes[$this->email][$path])) throw new \RuntimeException("No folder {$path}");

        return self::$boxes[$this->email][$path];
    }

    public function folders(): array
    {
        return collect(self::$boxes[$this->email])->map(fn ($f) => ['delimiter' => '/', 'flags' => $f['flags']])->all();
    }

    public function status(string $path): array
    {
        $f = $this->box($path);

        return ['uidvalidity' => $f['uidvalidity'], 'uidnext' => $f['next'], 'messages' => count($f['messages']),
            'unseen' => count(array_filter($f['messages'], fn ($m) => !in_array('\Seen', $m['flags'], true)))];
    }

    public function uids(string $path): array
    {
        return array_keys($this->box($path)['messages']);
    }

    public function fetch(string $path, array $uids): array
    {
        $out = [];
        foreach ($uids as $uid) {
            if ($m = $this->box($path)['messages'][$uid] ?? null) $out[$uid] = $m + ['size' => strlen($m['raw'])];
        }

        return $out;
    }

    public function flags(string $path, array $uids): array
    {
        $out = [];
        foreach ($uids as $uid) if (isset($this->box($path)['messages'][$uid])) $out[$uid] = $this->box($path)['messages'][$uid]['flags'];

        return $out;
    }

    public function setFlags(string $path, array $uids, array $flags, bool $on): void
    {
        $f = &$this->box($path);
        foreach ($uids as $uid) {
            if (!isset($f['messages'][$uid])) continue;
            $now = $f['messages'][$uid]['flags'];
            $f['messages'][$uid]['flags'] = array_values($on ? array_unique([...$now, ...$flags]) : array_diff($now, $flags));
        }
        self::$log[] = ['setFlags', $this->email, $path, $uids, $flags, $on];
    }

    public function move(string $path, array $uids, string $to): void
    {
        $f = &$this->box($path);
        foreach ($uids as $uid) {
            if (!isset($f['messages'][$uid])) continue;
            self::deliver($this->email, $f['messages'][$uid]['raw'], $to, $f['messages'][$uid]['flags']);
            unset($f['messages'][$uid]);
        }
        self::$log[] = ['move', $this->email, $path, $uids, $to];
    }

    public function delete(string $path, array $uids): void
    {
        $f = &$this->box($path);
        foreach ($uids as $uid) unset($f['messages'][$uid]);
        self::$log[] = ['delete', $this->email, $path, $uids];
    }

    public function append(string $path, string $raw, array $flags = []): void
    {
        self::deliver($this->email, $raw, $path, $flags);
        self::$log[] = ['append', $this->email, $path];
    }

    public function createFolder(string $path): void
    {
        self::$boxes[$this->email][$path] ??= ['flags' => [], 'uidvalidity' => 1, 'next' => 1, 'messages' => []];
    }

    public function close(): void
    {
    }
}
