<?php

namespace App\Services\Mail;

/**
 * One open connection to a mailbox's IMAP server. Folders are IMAP paths; messages
 * are addressed by UID. ImapGateway is the real one; FakeGateway keeps mail in memory
 * for tests.
 */
interface Gateway
{
    /** @return array<string, array{delimiter: string, flags: string[]}> path => details */
    public function folders(): array;

    /** @return array{uidvalidity: int, uidnext: int, messages: int, unseen: int} */
    public function status(string $path): array;

    /** @return int[] every UID in the folder */
    public function uids(string $path): array;

    /** @return array<int, array{raw: string, flags: string[], size: int}> uid => message, without marking anything read */
    public function fetch(string $path, array $uids): array;

    /** @return array<int, string[]> uid => flags */
    public function flags(string $path, array $uids): array;

    public function setFlags(string $path, array $uids, array $flags, bool $on): void;

    public function move(string $path, array $uids, string $to): void;

    /** Remove for good (\Deleted and expunge). */
    public function delete(string $path, array $uids): void;

    public function append(string $path, string $raw, array $flags = []): void;

    public function createFolder(string $path): void;

    public function close(): void;
}
