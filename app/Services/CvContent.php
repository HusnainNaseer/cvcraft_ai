<?php

namespace App\Services;

/**
 * Shapes CV content edited by the user into the template schema.
 *
 * Only known fields are kept, every value is plain text with a length cap, lists are bounded, and empty
 * entries are dropped. Fields the editor does not own (photo, colour theme, template meta) always come from
 * the stored CV, never from the request.
 */
class CvContent
{
    private const SHORT = 300;

    private const LONG = 3000;

    /** list name => [max items, field => max length] */
    private const LISTS = [
        'experience' => [30, [
            'title' => self::SHORT, 'company' => self::SHORT, 'period' => 100, 'location' => self::SHORT,
            'website' => self::SHORT, 'business_sector' => self::SHORT, 'department' => self::SHORT,
            'summary' => self::LONG, 'description' => self::LONG, 'projects' => self::LONG,
        ]],
        'education' => [20, [
            'degree' => self::SHORT, 'institution' => self::SHORT, 'date' => 100, 'location' => self::SHORT, 'details' => self::LONG,
        ]],
        'projects' => [30, [
            'title' => self::SHORT, 'subtitle' => self::SHORT, 'period' => 100, 'link' => self::SHORT, 'description' => self::LONG,
        ]],
        'certifications' => [30, [
            'title' => self::SHORT, 'type' => self::SHORT, 'detail' => self::SHORT,
        ]],
        'interests' => [20, [
            'title' => self::SHORT, 'description' => self::LONG,
        ]],
    ];

    private const PERSONAL = ['name', 'headline', 'email', 'phone', 'address', 'linkedin', 'github', 'website', 'date_of_birth', 'place_of_birth', 'nationality', 'gender', 'whatsapp'];

    private const LANGUAGE_FIELDS = ['listening', 'reading', 'speaking_production', 'speaking_interaction', 'writing'];

    /**
     * The part of a CV the editor works with (no photo, theme or template meta).
     */
    public function editable(array $cv): array
    {
        return $this->sanitize($cv, []);
    }

    /**
     * Clean edited content and merge it into the stored CV.
     */
    public function merge(array $stored, array $edited): array
    {
        $clean = $this->sanitize($edited, $stored);

        $personal = $clean['personal'];
        foreach (['photo', 'profile_photo'] as $key) {
            if (isset($stored['personal'][$key])) {
                $personal[$key] = $stored['personal'][$key];
            }
        }

        return [...$stored, ...$clean, 'personal' => $personal];
    }

    private function sanitize(array $cv, array $stored): array
    {
        $personal = [];
        foreach (self::PERSONAL as $field) {
            $personal[$field] = $this->text($cv['personal'][$field] ?? '', self::SHORT);
        }

        $result = [
            'personal' => $personal,
            'about' => $this->text($cv['about'] ?? '', self::LONG),
            'driving' => $this->text($cv['driving'] ?? '', self::SHORT),
            'hobbies' => $this->text($cv['hobbies'] ?? '', self::LONG),
        ];

        foreach (self::LISTS as $name => [$maxItems, $fields]) {
            $result[$name] = $this->entries($cv[$name] ?? [], $maxItems, $fields, $name === 'experience');
        }

        $result['skills'] = $this->skills($cv['skills'] ?? []);
        $result['languages'] = $this->languages($cv['languages'] ?? []);

        return $result;
    }

    private function entries(mixed $entries, int $maxItems, array $fields, bool $withHighlights): array
    {
        $clean = [];
        foreach (array_slice($this->list($entries), 0, $maxItems) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $item = [];
            foreach ($fields as $field => $max) {
                $item[$field] = $this->text($entry[$field] ?? '', $max);
            }
            if ($withHighlights) {
                $item['highlights'] = $this->strings($entry['highlights'] ?? [], 20, 600);
            }

            if (implode('', array_filter($item, 'is_string')) !== '' || ! empty($item['highlights'])) {
                $clean[] = $item;
            }
        }

        return $clean;
    }

    private function skills(mixed $groups): array
    {
        $clean = [];
        foreach (array_slice($this->list($groups), 0, 20) as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = $this->strings($group['items'] ?? [], 60, 100);
            if ($items !== []) {
                $clean[] = ['label' => $this->text($group['label'] ?? '', 100) ?: 'Skills', 'items' => $items];
            }
        }

        return $clean;
    }

    private function languages(mixed $languages): array
    {
        $languages = is_array($languages) ? $languages : [];
        $other = [];

        foreach (array_slice($this->list($languages['other'] ?? []), 0, 20) as $language) {
            $name = $this->text(is_array($language) ? ($language['name'] ?? '') : $language, 100);
            if ($name === '') {
                continue;
            }

            $entry = ['name' => $name];
            foreach (self::LANGUAGE_FIELDS as $field) {
                $entry[$field] = is_array($language) ? $this->text($language[$field] ?? '', 20) : '';
            }
            $other[] = $entry;
        }

        return ['mother' => $this->text($languages['mother'] ?? '', 100), 'other' => $other];
    }

    /**
     * @return string[]
     */
    private function strings(mixed $values, int $maxItems, int $maxLength): array
    {
        $clean = [];
        foreach ($this->list($values) as $value) {
            $value = $this->text($value, $maxLength);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return array_slice(array_values(array_unique($clean)), 0, $maxItems);
    }

    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function text(mixed $value, int $max): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        // Plain text only: drop control characters (keeping line breaks) and any markup.
        $text = preg_replace('/[^\P{C}\n]+/u', '', strip_tags((string) $value)) ?? '';
        $text = trim(preg_replace("/[ \t]+/u", ' ', $text) ?? '');

        return mb_substr($text, 0, $max);
    }
}
