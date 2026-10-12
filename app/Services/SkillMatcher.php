<?php

namespace App\Services;

/**
 * Keyword-based skill matching between a job description and a CV.
 */
class SkillMatcher
{
    private function detectSkills(string $text): array
    {
        $normalized = $this->normalizeSkillText($text);
        $compact = preg_replace('/[^a-z0-9+#]+/', '', $normalized) ?? '';
        $found = [];

        foreach ($this->skillDictionary() as $key => $skill) {
            foreach ($skill['aliases'] as $alias) {
                $aliasText = trim($this->normalizeSkillText($alias));
                $aliasCompact = preg_replace('/[^a-z0-9+#]+/', '', $aliasText) ?? '';
                $pattern = '/(?<![a-z0-9+#])'.preg_quote($aliasText, '/').'(?![a-z0-9+#])/i';

                if (
                    preg_match($pattern, $normalized)
                    || (strlen($aliasCompact) >= 3 && str_contains($compact, $aliasCompact))
                ) {
                    $found[$key] = [
                        'key' => $key,
                        'label' => $skill['label'],
                    ];
                    break;
                }
            }
        }

        return array_values($found);
    }

    /**
     * Compare the skills a job asks for with the skills found in a CV.
     *
     * @return array{required: string[], matched: string[], missing: string[], score: ?int, matchedCount: int, requiredCount: int}
     */
    public function match(string $jobDescription, string $cvText): array
    {
        $required = $this->mergeSkillLists(
            $this->detectSkills($jobDescription),
            $this->extractSkillPhrases($jobDescription)
        );
        $cvSkills = $this->mergeSkillLists(
            $this->detectSkills($cvText),
            $this->extractSkillPhrases($cvText)
        );
        $cvSkillKeys = array_column($cvSkills, 'key');
        $normalizedCvText = $this->normalizeSkillText($cvText);

        $matched = [];
        $missing = [];

        foreach ($required as $skill) {
            if (
                in_array($skill['key'], $cvSkillKeys, true)
                || $this->hasRelatedSkillKey($skill['key'], $cvSkillKeys)
                || $this->containsSkillPhrase($normalizedCvText, $skill['label'])
            ) {
                $matched[] = $skill['label'];
            } else {
                $missing[] = $skill['label'];
            }
        }

        $requiredCount = count($required);
        $matched = array_values(array_unique($matched));
        $missing = array_values(array_unique($missing));
        $matchedCount = count($matched);
        $score = $requiredCount > 0 ? (int) round(($matchedCount / $requiredCount) * 100) : null;

        return [
            'required' => array_column($required, 'label'),
            'matched' => $matched,
            'missing' => $missing,
            'score' => $score,
            'matchedCount' => $matchedCount,
            'requiredCount' => $requiredCount,
        ];
    }

    private function mergeSkillLists(array ...$skillLists): array
    {
        $merged = [];

        foreach ($skillLists as $skills) {
            foreach ($skills as $skill) {
                if (! is_array($skill) || blank($skill['key'] ?? null) || blank($skill['label'] ?? null)) {
                    continue;
                }

                $merged[$skill['key']] = [
                    'key' => $skill['key'],
                    'label' => $skill['label'],
                ];
            }
        }

        return array_values($merged);
    }

    private function extractSkillPhrases(string $text): array
    {
        $chunks = $this->candidateSkillChunks($text);
        $skills = [];

        foreach ($chunks as $chunk) {
            foreach ($this->splitSkillChunk($chunk) as $candidate) {
                $label = $this->cleanSkillLabel($candidate);
                if (! $this->isUsefulSkillPhrase($label)) {
                    continue;
                }

                $key = $this->skillKey($label);
                if ($key !== '') {
                    $skills[$key] = [
                        'key' => $key,
                        'label' => $label,
                    ];
                }
            }
        }

        return array_values($skills);
    }

    private function candidateSkillChunks(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R+/', $text) ?: [];
        $chunks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/(?:skills?|technolog(?:y|ies)|tools?|stack|frameworks?|libraries|requirements?|experience with|knowledge of|proficient in|familiar with)\s*:?\s*(.+)$/i', $line, $match)) {
                $chunks[] = $match[1];
                continue;
            }

            if (preg_match('/^\s*(?:[-*•]|\d+[.)])\s*(.+)$/u', $line, $match)) {
                $chunks[] = $match[1];
                continue;
            }

        }

        return $chunks;
    }

    private function splitSkillChunk(string $chunk): array
    {
        $chunk = preg_replace('/\b(?:and|or|required|preferred|must have|nice to have|experience with|knowledge of)\b/i', ',', $chunk) ?? $chunk;

        return preg_split('/\s*(?:,|;|\||\/|·|•)\s*/u', $chunk) ?: [];
    }

    private function cleanSkillLabel(string $label): string
    {
        $label = trim($label);
        $label = preg_replace('/^[^A-Za-z0-9+#.]+|[^A-Za-z0-9+#)]+$/u', '', $label) ?? $label;
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return trim($label);
    }

    private function isUsefulSkillPhrase(string $label): bool
    {
        if ($label === '') {
            return false;
        }

        $key = $this->skillKey($label);
        $wordCount = str_word_count($label);
        $stop = [
            'job', 'description', 'candidate', 'developer', 'engineer', 'manager', 'team',
            'company', 'work', 'role', 'responsibilities', 'requirements', 'experience',
            'skills', 'tools', 'technology', 'technologies', 'good', 'strong', 'excellent',
        ];

        if (strlen($key) < 2 || in_array($key, $stop, true)) {
            return false;
        }

        if ($wordCount > 3 || strlen($label) > 32) {
            return false;
        }

        // Sentence fragments ("if necessary", "should know HTML", "of developing web services").
        if (preg_match('/^(?:of|to|in|on|for|with|and|or|the|a|an|be|is|are|if|it|our|your|we|you|should|must|will|can|know|write|apply|ability|proven|proficiency|proficient|knowledge|understanding|familiarity)\b/i', $label) === 1) {
            return false;
        }

        // Technology names: inner capitals (PostgreSQL, WordPress), all caps (AWS), digits or symbols (Next.js, C#),
        // or a technical noun. A capital first letter alone is just sentence case, not a skill.
        return preg_match('/^.+[A-Z]|[0-9+#.]/', $label) === 1
            || preg_match('/\b(?:apis?|sql|css|html|js|frameworks?|librar(?:y|ies)|databases?|cloud|servers?|testing|security)\b/i', $label) === 1;
    }

    private function skillKey(string $label): string
    {
        $key = strtolower($label);
        $key = preg_replace('/\b(html)\s*5\b/i', '$1', $key) ?? $key;
        $key = preg_replace('/\b(css)\s*3\b/i', '$1', $key) ?? $key;
        $key = preg_replace('/[^a-z0-9+#.]+/', '', $key) ?? '';

        return trim($key);
    }

    private function containsSkillPhrase(string $normalizedText, string $label): bool
    {
        $phrase = trim($this->normalizeSkillText($label));
        if ($phrase === '') {
            return false;
        }

        $pattern = '/(?<![a-z0-9+#])'.preg_quote($phrase, '/').'(?![a-z0-9+#])/i';

        return preg_match($pattern, $normalizedText) === 1;
    }

    private function hasRelatedSkillKey(string $requiredKey, array $cvSkillKeys): bool
    {
        if (strlen($requiredKey) < 4) {
            return false;
        }

        foreach ($cvSkillKeys as $cvKey) {
            if (strlen($cvKey) < 4) {
                continue;
            }

            if (str_contains($requiredKey, $cvKey) || str_contains($cvKey, $requiredKey)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSkillText(string $text): string
    {
        $text = strtolower($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\bhtml\s*5\b/i', 'html', $text) ?? $text;
        $text = preg_replace('/\bcss\s*3\b/i', 'css', $text) ?? $text;
        $text = preg_replace('/\bes\s*6\b/i', 'javascript', $text) ?? $text;
        $text = str_replace(['-', '_', '/', '\\', "\r", "\n", "\t", '&', ',', ';', '|', '·', '•'], ' ', $text);
        $text = preg_replace('/(?<=[a-z])(?=\d)|(?<=\d)(?=[a-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return ' '.trim($text).' ';
    }

    private function skillDictionary(): array
    {
        return [
            'php' => ['label' => 'PHP', 'aliases' => ['PHP']],
            'laravel' => ['label' => 'Laravel', 'aliases' => ['Laravel']],
            'mysql' => ['label' => 'MySQL', 'aliases' => ['MySQL', 'MariaDB']],
            'sql' => ['label' => 'SQL', 'aliases' => ['SQL', 'database queries']],
            'javascript' => ['label' => 'JavaScript', 'aliases' => ['JavaScript', 'Java Script', 'JS', 'ES6', 'Vanilla JavaScript']],
            'typescript' => ['label' => 'TypeScript', 'aliases' => ['TypeScript', 'TS']],
            'html' => ['label' => 'HTML', 'aliases' => ['HTML', 'HTML5', 'HTML 5']],
            'css' => ['label' => 'CSS', 'aliases' => ['CSS', 'CSS3', 'CSS 3']],
            'bootstrap' => ['label' => 'Bootstrap', 'aliases' => ['Bootstrap']],
            'tailwind' => ['label' => 'Tailwind CSS', 'aliases' => ['Tailwind', 'Tailwind CSS']],
            'jquery' => ['label' => 'jQuery', 'aliases' => ['jQuery']],
            'vue' => ['label' => 'Vue.js', 'aliases' => ['Vue', 'Vue.js', 'VueJS']],
            'react' => ['label' => 'React', 'aliases' => ['React', 'React.js', 'ReactJS']],
            'node' => ['label' => 'Node.js', 'aliases' => ['Node', 'Node.js', 'NodeJS']],
            'api' => ['label' => 'REST API', 'aliases' => ['REST API', 'REST APIs', 'RESTful', 'API development', 'APIs']],
            'mvc' => ['label' => 'MVC', 'aliases' => ['MVC', 'Model View Controller']],
            'oop' => ['label' => 'OOP', 'aliases' => ['OOP', 'Object Oriented', 'Object-Oriented']],
            'git' => ['label' => 'Git', 'aliases' => ['Git', 'GitHub', 'GitLab', 'version control']],
            'docker' => ['label' => 'Docker', 'aliases' => ['Docker', 'containerization', 'containers']],
            'aws' => ['label' => 'AWS', 'aliases' => ['AWS', 'Amazon Web Services']],
            'linux' => ['label' => 'Linux', 'aliases' => ['Linux', 'Ubuntu']],
            'redis' => ['label' => 'Redis', 'aliases' => ['Redis']],
            'postgresql' => ['label' => 'PostgreSQL', 'aliases' => ['PostgreSQL', 'Postgres']],
            'python' => ['label' => 'Python', 'aliases' => ['Python']],
            'ajax' => ['label' => 'AJAX', 'aliases' => ['AJAX']],
            'json' => ['label' => 'JSON', 'aliases' => ['JSON']],
            'xml' => ['label' => 'XML', 'aliases' => ['XML']],
            'agile' => ['label' => 'Agile', 'aliases' => ['Agile', 'Scrum']],
            'testing' => ['label' => 'Testing', 'aliases' => ['Testing', 'Unit Testing', 'PHPUnit', 'Pest']],
            'security' => ['label' => 'Security', 'aliases' => ['Security', 'Authentication', 'Authorization', 'RBAC']],
            'eloquent' => ['label' => 'Eloquent ORM', 'aliases' => ['Eloquent', 'Eloquent ORM']],
            'blade' => ['label' => 'Blade', 'aliases' => ['Blade', 'Blade templates']],
            'livewire' => ['label' => 'Livewire', 'aliases' => ['Livewire']],
            'inertia' => ['label' => 'Inertia.js', 'aliases' => ['Inertia', 'Inertia.js']],
        ];
    }
}
