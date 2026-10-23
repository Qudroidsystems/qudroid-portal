<?php

namespace App\Services\Mail;

use Webklex\PHPIMAP\Message;

/** Turns a raw RFC 822 message into the fields Qmail stores. */
class MailParser
{
    public static function parse(string $raw): array
    {
        $m = Message::fromString($raw);
        $addresses = function ($attr) {
            $out = [];
            foreach (($attr ? $attr->toArray() : []) as $a) {
                if (is_object($a) && !empty($a->mail)) $out[] = ['email' => mb_strtolower(trim($a->mail)), 'name' => trim((string) ($a->personal ?? ''), " \"'")];
            }

            return $out;
        };
        $id = fn ($v) => ($s = trim((string) $v, " <>\t\r\n")) === '' ? null : mb_substr($s, 0, 191);
        $refs = [];
        if (preg_match_all('/<([^>]+)>/', (string) ($m->getHeader()?->get('references') ?? ''), $mm)) $refs = $mm[1];
        if (!$refs && ($r = (string) $m->getReferences()) !== '') $refs = preg_split('/[\s,]+/', trim($r, '<> '));
        $refs = array_values(array_filter(array_map(fn ($r) => trim($r, '<> '), $refs)));

        $html = $m->hasHTMLBody() ? $m->getHTMLBody() : null;
        $text = $m->hasTextBody() ? $m->getTextBody() : null;
        if ($text === null && $html !== null) $text = self::htmlToText($html);

        $attachments = [];
        foreach ($m->getAttachments() as $a) {
            $content = (string) $a->getContent();
            $cid = $a->id && strtolower((string) $a->disposition) !== 'attachment' ? trim((string) $a->id, '<> ') : null;
            $attachments[] = [
                'filename' => mb_substr((string) ($a->name ?: $a->filename ?: 'attachment'), 0, 255), 'mime' => mb_substr((string) $a->content_type, 0, 150),
                'size' => strlen($content), 'content_id' => $cid ? mb_substr($cid, 0, 191) : null,
                'inline' => strtolower((string) $a->disposition) === 'inline' && $cid, 'content' => $content,
            ];
        }

        $from = $addresses($m->getFrom())[0] ?? null;
        $date = null;
        try { $date = $m->getDate()?->toDate()?->setTimezone(config('app.timezone')); } catch (\Throwable $e) {}

        return [
            'message_id' => $id($m->getMessageId()),
            'in_reply_to' => $id($m->getInReplyTo()),
            'references' => $refs,
            'subject' => mb_substr(self::decode((string) $m->getSubject()), 0, 500),
            'from_email' => $from['email'] ?? null, 'from_name' => $from['name'] ?? null,
            'to' => $addresses($m->getTo()), 'cc' => $addresses($m->getCc()), 'bcc' => $addresses($m->getBcc()), 'reply_to' => $addresses($m->getReplyTo()),
            'sent_at' => $date,
            'body_text' => $text, 'body_html' => $html,
            'snippet' => self::snippet($text ?? ''),
            'attachments' => $attachments,
        ];
    }

    protected static function decode(string $s): string
    {
        return str_contains($s, '=?') ? (iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $s) : $s;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace(['#<(script|style|head)[^>]*>.*?</\1>#si', '#<br\s*/?>#i', '#</(p|div|li|tr|h[1-6])>#i'], ['', "\n", "\n"], $html);

        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function snippet(string $text): string
    {
        // Skip quoted replies ("> ...", "On ... wrote:") so the preview shows what's new.
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) as $l) {
            if (str_starts_with(ltrim($l), '>') || preg_match('/^On .{5,200} wrote:$/', trim($l))) break;
            $lines[] = $l;
        }

        return mb_strimwidth(trim((string) preg_replace('/\s+/u', ' ', implode(' ', $lines))), 0, 200, '…');
    }

    public static function threadKey(?string $messageId, ?string $inReplyTo, array $references, ?string $subject): string
    {
        $root = $references[0] ?? $inReplyTo ?? $messageId;

        return md5($root ? mb_strtolower($root) : 'subject:' . self::baseSubject($subject));
    }

    public static function baseSubject(?string $subject): string
    {
        return mb_strtolower(trim((string) preg_replace('/^((re|fw|fwd|aw|sv)\s*(\[\d+\])?:\s*)+/i', '', (string) $subject)));
    }
}
