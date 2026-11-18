<?php

namespace App\Support;

/**
 * Safely formats the small subset of Markdown used by AI replies.
 * HTML is escaped first, so the output cannot introduce XSS.
 */
class AiText
{
    public static function format(?string $text): string
    {
        $safe = htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Bold and italic formatting commonly returned by AI models.
        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe);
        $safe = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe);

        return nl2br($safe, false);
    }
}
