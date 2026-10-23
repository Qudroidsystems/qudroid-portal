<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\IMAP;

/** IMAP through webklex/php-imap's protocol layer (no PHP imap extension needed). */
class ImapGateway implements Gateway
{
    protected Client $client;
    protected ImapProtocol $imap;
    protected ?string $selected = null;

    public function __construct(MailAccount $account)
    {
        $enc = match ($account->imap_encryption) { 'ssl' => 'ssl', 'tls' => 'starttls', default => false };
        $this->client = (new ClientManager())->make([
            'host' => $account->imap_host, 'port' => (int) $account->imap_port, 'encryption' => $enc,
            'validate_cert' => (bool) config('qudroid.mail.validate_cert', true),
            'username' => $account->username, 'password' => $account->password, 'protocol' => 'imap', 'timeout' => 20,
        ]);
        $this->client->connect();
        $this->imap = $this->client->getConnection();
    }

    protected function select(string $path): void
    {
        if ($this->selected === $path) return;
        $this->imap->selectFolder($path)->validatedData();
        $this->selected = $path;
    }

    public function folders(): array
    {
        return $this->imap->folders()->validatedData() ?: [];
    }

    public function status(string $path): array
    {
        $s = $this->imap->folderStatus($path, ['MESSAGES', 'UNSEEN', 'UIDNEXT', 'UIDVALIDITY'])->validatedData();

        return ['uidvalidity' => (int) ($s['uidvalidity'] ?? 0), 'uidnext' => (int) ($s['uidnext'] ?? 0), 'messages' => (int) ($s['messages'] ?? 0), 'unseen' => (int) ($s['unseen'] ?? 0)];
    }

    public function uids(string $path): array
    {
        $this->select($path);

        return array_map('intval', $this->imap->search(['ALL'])->validatedData() ?: []);
    }

    public function fetch(string $path, array $uids): array
    {
        if (!$uids) return [];
        $this->select($path);
        $out = [];
        foreach ($this->imap->fetch(['UID', 'FLAGS', 'RFC822.SIZE', 'BODY.PEEK[]'], array_values($uids), null, IMAP::ST_UID)->validatedData() as $uid => $d) {
            if (!is_array($d) || !isset($d['BODY[]'])) continue;
            $out[(int) $uid] = ['raw' => (string) $d['BODY[]'], 'flags' => (array) ($d['FLAGS'] ?? []), 'size' => (int) ($d['RFC822.SIZE'] ?? strlen($d['BODY[]']))];
        }

        return $out;
    }

    public function flags(string $path, array $uids): array
    {
        if (!$uids) return [];
        $this->select($path);
        $out = [];
        foreach ($this->imap->fetch(['UID', 'FLAGS'], array_values($uids), null, IMAP::ST_UID)->validatedData() as $uid => $d) {
            $out[(int) $uid] = (array) ($d['FLAGS'] ?? []);
        }

        return $out;
    }

    public function setFlags(string $path, array $uids, array $flags, bool $on): void
    {
        if (!$uids) return;
        $this->select($path);
        foreach ($uids as $uid) $this->imap->store($flags, (int) $uid, null, $on ? '+' : '-', true, IMAP::ST_UID)->validatedData();
    }

    public function move(string $path, array $uids, string $to): void
    {
        if (!$uids) return;
        $this->select($path);
        $this->imap->moveManyMessages(array_values($uids), $to, IMAP::ST_UID)->validatedData();
    }

    public function delete(string $path, array $uids): void
    {
        if (!$uids) return;
        $this->setFlags($path, $uids, ['\Deleted'], true);
        $this->imap->expunge()->validatedData();
    }

    public function append(string $path, string $raw, array $flags = []): void
    {
        $this->imap->appendMessage($path, $raw, $flags ?: null)->validatedData();
    }

    public function createFolder(string $path): void
    {
        $this->imap->createFolder($path)->validatedData();
        $this->imap->subscribeFolder($path);
    }

    public function close(): void
    {
        try { $this->client->disconnect(); } catch (\Throwable $e) {}
    }
}
