<?php

namespace App\Services;

/**
 * Keeps an AI-tailored CV faithful to the candidate's original CV.
 *
 * The deterministic checks here need no AI: names of employers, schools, certificates and projects, short
 * factual fields (sector, department, location, links), dates, numbers, skills and language levels must
 * all be traceable to the source text, otherwise they are removed. Free-text sentences that pass these
 * checks are handed to an AI fact-checker through claims() / applyVerdicts().
 */
class CvFactGuard
{
    private const STOPWORDS = [
        'a', 'an', 'and', 'or', 'of', 'the', 'in', 'on', 'at', 'for', 'with', 'to', 'by', 'from', 'as', 'via', 'into', 'using',
    ];

    /** Phrases that show the CV itself names a mother tongue. */
    private const MOTHER_TONGUE_PATTERN = '/\b(mother\s*tongue|native|first\s+language)\b/i';

    private const LANGUAGE_LEVEL_FIELDS = ['listening', 'reading', 'speaking_production', 'speaking_interaction', 'writing'];

    /** @var array<string, true> normalised source words */
    private array $words = [];

    /** @var array<string, true> five-letter prefixes of longer source words */
    private array $prefixes = [];

    private string $flatSource = '';

    /** Lowercased source with whitespace collapsed, for e-mail and URL lookups. */
    private string $lowerSource = '';

    private string $digits = '';

    /** @var string[] human-readable notes of what was removed */
    private array $removed = [];

    /**
     * Remove everything in the CV that the source text does not support.
     *
     * @return array{cv: array, removed: string[]}
     */
    public function enforce(array $cv, string $source): array
    {
        $this->index($source);
        $this->removed = [];

        $cv['personal'] = $this->personal(is_array($cv['personal'] ?? null) ? $cv['personal'] : []);
        $cv['experience'] = $this->entries($cv['experience'] ?? [], fn (array $entry) => $this->experience($entry));
        $cv['education'] = $this->entries($cv['education'] ?? [], fn (array $entry) => $this->education($entry));
        $cv['projects'] = $this->entries($cv['projects'] ?? [], fn (array $entry) => $this->project($entry));
        $cv['certifications'] = $this->entries($cv['certifications'] ?? [], fn (array $entry) => $this->certification($entry));
        $cv['skills'] = $this->skills($cv['skills'] ?? []);
        $cv['languages'] = $this->languages(is_array($cv['languages'] ?? null) ? $cv['languages'] : []);
        $cv['interests'] = $this->entries($cv['interests'] ?? [], fn (array $entry) => $this->interest($entry));
        $cv['about'] = $this->prose((string) ($cv['about'] ?? ''), 'About');
        $cv['hobbies'] = $this->prose((string) ($cv['hobbies'] ?? ''), 'Hobbies');
        $cv['driving'] = $this->supported((string) ($cv['driving'] ?? '')) ? (string) ($cv['driving'] ?? '') : $this->drop('Driving licence', (string) ($cv['driving'] ?? ''));

        return ['cv' => $cv, 'removed' => $this->removed];
    }

    /**
     * Free-text fields an AI fact-checker should review, keyed by their dot path in the CV.
     *
     * @return array<string, string>
     */
    public function claims(array $cv): array
    {
        $claims = [];
        $add = function (string $path, mixed $value) use (&$claims) {
            if (is_string($value) && trim($value) !== '') {
                $claims[$path] = trim($value);
            }
        };

        $add('personal.headline', $cv['personal']['headline'] ?? '');
        $add('about', $cv['about'] ?? '');
        $add('hobbies', $cv['hobbies'] ?? '');

        foreach ((array) ($cv['experience'] ?? []) as $i => $entry) {
            foreach (['title', 'summary', 'description', 'projects'] as $field) {
                $add("experience.$i.$field", $entry[$field] ?? '');
            }
            foreach ((array) ($entry['highlights'] ?? []) as $h => $highlight) {
                $add("experience.$i.highlights.$h", $highlight);
            }
        }
        foreach ((array) ($cv['education'] ?? []) as $i => $entry) {
            $add("education.$i.degree", $entry['degree'] ?? '');
            $add("education.$i.details", $entry['details'] ?? '');
        }
        foreach ((array) ($cv['projects'] ?? []) as $i => $entry) {
            $add("projects.$i.subtitle", $entry['subtitle'] ?? '');
            $add("projects.$i.description", $entry['description'] ?? '');
        }
        foreach ((array) ($cv['interests'] ?? []) as $i => $entry) {
            $add("interests.$i.description", $entry['description'] ?? '');
        }

        return $claims;
    }

    /**
     * Apply fact-checker verdicts: "keep", "fix" (use the corrected text) or "remove".
     *
     * @param  array<string, array{verdict: string, text?: string}>  $verdicts  keyed by claim path
     * @return array{cv: array, removed: string[]}
     */
    public function applyVerdicts(array $cv, array $verdicts, string $source): array
    {
        $removed = [];

        foreach ($verdicts as $path => $verdict) {
            $original = data_get($cv, $path);
            if (! is_string($original)) {
                continue;
            }

            if ($verdict['verdict'] === 'remove') {
                data_set($cv, $path, '');
                $removed[] = $this->label($path).': "'.$this->shorten($original).'"';
            } elseif ($verdict['verdict'] === 'fix' && trim((string) ($verdict['text'] ?? '')) !== '') {
                data_set($cv, $path, trim($verdict['text']));
                $removed[] = $this->label($path).': reworded "'.$this->shorten($original).'"';
            }
        }

        // Drop highlights emptied above, then re-run the deterministic checks over the corrected text.
        foreach ((array) ($cv['experience'] ?? []) as $i => $entry) {
            if (isset($entry['highlights']) && is_array($entry['highlights'])) {
                $cv['experience'][$i]['highlights'] = array_values(array_filter($entry['highlights'], fn ($h) => is_string($h) && trim($h) !== ''));
            }
        }

        $checked = $this->enforce($cv, $source);

        return ['cv' => $checked['cv'], 'removed' => [...$removed, ...$checked['removed']]];
    }

    /**
     * Remove sentences from free text that contain numbers the source does not (e.g. invented metrics).
     */
    public function proseWithKnownNumbers(string $text, string $source): string
    {
        $this->index($source);

        return $this->prose($text, '');
    }

    /**
     * True when every meaningful word (and number) of the value appears in the source.
     */
    public function supports(string $source, string $value): bool
    {
        $this->index($source);

        return $this->supported($value);
    }

    private function index(string $source): void
    {
        $this->flatSource = $this->flatten($source);
        $this->lowerSource = mb_strtolower(preg_replace('/\s+/', ' ', $source) ?? '');
        $this->digits = preg_replace('/\D+/', '', $source) ?? '';
        $this->words = [];
        $this->prefixes = [];

        foreach (explode(' ', $this->flatSource) as $word) {
            if ($word === '') {
                continue;
            }
            $word = $this->stem($word);
            $this->words[$word] = true;
            if (mb_strlen($word) >= 5) {
                $this->prefixes[mb_substr($word, 0, 5)] = true;
            }
        }
    }

    private function personal(array $personal): array
    {
        foreach (['date_of_birth', 'place_of_birth', 'nationality', 'gender', 'address', 'whatsapp'] as $field) {
            $value = (string) ($personal[$field] ?? '');
            if ($value !== '' && ! $this->supported($value)) {
                $personal[$field] = $this->drop(str_replace('_', ' ', ucfirst($field)), $value);
            }
        }

        $email = strtolower(trim((string) ($personal['email'] ?? '')));
        if ($email !== '' && ! str_contains($this->lowerSource, $email)) {
            $personal['email'] = $this->drop('Email', $email);
        }

        $personal['headline'] = $this->prose((string) ($personal['headline'] ?? ''), 'Headline');

        foreach (['linkedin' => 'LinkedIn', 'github' => 'GitHub', 'website' => 'Website'] as $field => $label) {
            $personal[$field] = $this->link((string) ($personal[$field] ?? ''), $label);
        }

        $phoneDigits = preg_replace('/\D+/', '', (string) ($personal['phone'] ?? '')) ?? '';
        if ($phoneDigits !== '' && ! str_contains($this->digits, $phoneDigits)) {
            $personal['phone'] = $this->drop('Phone', (string) $personal['phone']);
        }

        return $personal;
    }

    private function experience(array $entry): ?array
    {
        $company = (string) ($entry['company'] ?? '');
        if ($company !== '' && ! $this->supported($company)) {
            $this->drop('Work experience', trim(($entry['title'] ?? '').' at '.$company));

            return null;
        }

        foreach (['business_sector', 'department', 'location'] as $field) {
            $entry[$field] = $this->field($entry, $field, ucfirst(str_replace('_', ' ', $field)));
        }
        $entry['website'] = $this->link((string) ($entry['website'] ?? ''), 'Website');
        $entry['period'] = $this->dates((string) ($entry['period'] ?? ''), 'Dates');

        foreach (['summary', 'description', 'projects'] as $field) {
            if (is_string($entry[$field] ?? null)) {
                $entry[$field] = $this->prose($entry[$field], 'Experience');
            }
        }
        if (is_array($entry['highlights'] ?? null)) {
            $entry['highlights'] = array_values(array_filter(array_map(
                fn ($highlight) => is_string($highlight) ? $this->prose($highlight, 'Highlight') : '',
                $entry['highlights']
            )));
        }

        return $this->hasText($entry, ['title', 'company', 'summary', 'description']) || ! empty($entry['highlights']) ? $entry : null;
    }

    private function education(array $entry): ?array
    {
        $institution = (string) ($entry['institution'] ?? '');
        if ($institution !== '' && ! $this->supported($institution)) {
            $this->drop('Education', trim(($entry['degree'] ?? '').' at '.$institution));

            return null;
        }

        $entry['location'] = $this->field($entry, 'location', 'Location');
        $entry['date'] = $this->dates((string) ($entry['date'] ?? ''), 'Dates');
        if (is_string($entry['details'] ?? null)) {
            $entry['details'] = $this->prose($entry['details'], 'Education');
        }

        return $this->hasText($entry, ['degree', 'institution', 'details']) ? $entry : null;
    }

    private function project(array $entry): ?array
    {
        $title = (string) ($entry['title'] ?? '');
        if ($title !== '' && ! $this->supported($title)) {
            $this->drop('Project', $title);

            return null;
        }

        $entry['link'] = $this->link((string) ($entry['link'] ?? ''), 'Project link');
        $entry['period'] = $this->dates((string) ($entry['period'] ?? ''), 'Dates');
        if (is_string($entry['description'] ?? null)) {
            $entry['description'] = $this->prose($entry['description'], 'Project');
        }

        return $this->hasText($entry, ['title', 'subtitle', 'description']) ? $entry : null;
    }

    private function certification(array $entry): ?array
    {
        $title = (string) ($entry['title'] ?? '');
        if ($title === '' || ! $this->supported($title)) {
            $this->drop('Certification', $title);

            return null;
        }

        $entry['type'] = $this->field($entry, 'type', 'Certification issuer');
        $entry['detail'] = $this->field($entry, 'detail', 'Certification detail');

        return $entry;
    }

    private function interest(array $entry): ?array
    {
        $entry['title'] = $this->field($entry, 'title', 'Interest');
        $entry['description'] = $this->prose((string) ($entry['description'] ?? ''), 'Interests');

        return $this->hasText($entry, ['title', 'description']) ? $entry : null;
    }

    private function skills(mixed $groups): array
    {
        $kept = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                $this->supported($item) ? $items[] = $item : $this->drop('Skill', $item);
            }

            if ($items !== []) {
                $kept[] = [...$group, 'items' => array_values(array_unique($items))];
            }
        }

        return $kept;
    }

    private function languages(array $languages): array
    {
        $other = [];
        foreach ((array) ($languages['other'] ?? []) as $language) {
            if (! is_array($language) || ! $this->supported((string) ($language['name'] ?? ''))) {
                is_array($language) && $this->drop('Language', (string) ($language['name'] ?? ''));

                continue;
            }

            foreach (self::LANGUAGE_LEVEL_FIELDS as $field) {
                $level = trim((string) ($language[$field] ?? ''));
                if ($level !== '' && ! $this->supported($level)) {
                    $language[$field] = '';
                    $this->drop('Language level', $language['name'].' '.$level);
                }
            }
            $other[] = $language;
        }

        $mother = trim((string) ($languages['mother'] ?? ''));
        if ($mother !== '' && ! $this->supported($mother)) {
            $this->drop('Mother tongue', $mother);
            $mother = '';
        } elseif ($mother !== '' && preg_match(self::MOTHER_TONGUE_PATTERN, $this->flatSource) !== 1) {
            // The CV lists the language but never says it is the mother tongue: keep it as a plain language.
            $this->removed[] = "Mother tongue: \"$mother\" (the CV does not say which language is native)";
            $names = array_map(fn ($language) => strtolower((string) ($language['name'] ?? '')), $other);
            if (! in_array(strtolower($mother), $names, true)) {
                $other[] = ['name' => $mother];
            }
            $mother = '';
        }

        return [...$languages, 'mother' => $mother, 'other' => $other];
    }

    /**
     * Drop sentences that contain numbers the source does not have.
     */
    private function prose(string $text, string $label): string
    {
        $text = trim($text);
        if ($text === '' || ! preg_match('/\d/', $text)) {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $kept = [];
        foreach ($sentences as $sentence) {
            if ($this->numbersSupported($sentence)) {
                $kept[] = $sentence;
            } elseif ($label !== '') {
                $this->drop($label, $sentence);
            }
        }

        return trim(implode(' ', $kept));
    }

    private function numbersSupported(string $text): bool
    {
        // Each number must appear as its own value in the source ("40%" needs a "40"), not merely as digits
        // inside a longer number such as a phone number. Letters around it are fine ("N+1", "C1", "Laravel 10").
        preg_match_all('/\d+(?:[.,]\d+)*/', $text, $matches);
        foreach ($matches[0] as $number) {
            if (preg_match('/(?<![\d.,])'.preg_quote($number, '/').'(?![\d]|[.,]\d)/', $this->lowerSource) === 1) {
                continue;
            }

            // "1,000" written as "1000" (or the reverse) still counts.
            $plain = str_replace([',', '.'], '', $number);
            if (! preg_match('/(?<![\d.,])'.preg_quote($plain, '/').'(?![\d]|[.,]\d)/', str_replace(',', '', $this->lowerSource))) {
                return false;
            }
        }

        return true;
    }

    private function dates(string $value, string $label): string
    {
        preg_match_all('/\b(19|20)\d{2}\b/', $value, $years);
        foreach ($years[0] as $year) {
            if (! isset($this->words[$year])) {
                return $this->drop($label, $value);
            }
        }

        return $value;
    }

    private function link(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $bare = strtolower(preg_replace('~^https?://(www\.)?~i', '', rtrim($value, '/')) ?? '');

        return $bare !== '' && str_contains($this->lowerSource, $bare) ? $value : $this->drop($label, $value);
    }

    private function field(array $entry, string $field, string $label): string
    {
        $value = trim((string) ($entry[$field] ?? ''));

        return $value === '' || $this->supported($value) ? $value : $this->drop($label, $value);
    }

    private function supported(string $value): bool
    {
        if (! $this->numbersSupported($value)) {
            return false;
        }

        foreach (explode(' ', $this->flatten($value)) as $token) {
            if ($token === '' || in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $token = $this->stem($token);
            if (isset($this->words[$token])) {
                continue;
            }
            if (mb_strlen($token) >= 5 && isset($this->prefixes[mb_substr($token, 0, 5)])) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function flatten(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}+#]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function stem(string $word): string
    {
        return mb_strlen($word) >= 4 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss') ? mb_substr($word, 0, -1) : $word;
    }

    private function hasText(array $entry, array $fields): bool
    {
        foreach ($fields as $field) {
            if (is_string($entry[$field] ?? null) && trim($entry[$field]) !== '') {
                return true;
            }
        }

        return false;
    }

    private function entries(mixed $entries, callable $check): array
    {
        $kept = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (is_array($entry) && ($checked = $check($entry)) !== null) {
                $kept[] = $checked;
            }
        }

        return $kept;
    }

    private function drop(string $label, string $value): string
    {
        if (trim($value) !== '') {
            $this->removed[] = $label.': "'.$this->shorten($value).'"';
        }

        return '';
    }

    private function label(string $path): string
    {
        $section = explode('.', $path)[0];

        return ucfirst($section === 'about' ? 'About' : $section);
    }

    private function shorten(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strlen($value) > 90 ? mb_substr($value, 0, 87).'...' : $value;
    }
}
