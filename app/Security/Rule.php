<?php

namespace App\Security;

abstract class Rule implements SecurityRule
{
    /**
     * Run a set of regex patterns over contents and build one finding per match.
     *
     * @param  array<int, array{pattern: string, description: string}>  $patterns
     * @return RuleFinding[]
     */
    protected function inspectPatterns(string $relativePath, string $contents, array $patterns): array
    {
        $findings = [];

        foreach ($patterns as $criterion) {
            $matches = $this->matches($contents, $criterion['pattern']);

            foreach ($matches as [$offset]) {
                $findings[] = $this->finding($relativePath, $criterion['description'], $contents, $offset);
            }
        }

        return $findings;
    }

    /** @return array<int, array{0: int}> list of [offset] in match order */
    private function matches(string $contents, string $pattern): array
    {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        return array_map(
            fn (array $match): array => [$match[1]],
            $matches[0] ?? [],
        );
    }

    protected function finding(string $file, string $description, string $contents, int $offset): RuleFinding
    {
        [$line, $snippet] = $this->lineFor($contents, $offset);

        return new RuleFinding(
            rule: $this->id(),
            severity: $this->severity()->value,
            file: $file,
            line: $line,
            snippet: $snippet,
            description: $description,
        );
    }

    /** @return array{0: int, 1: string|null} */
    private function lineFor(string $contents, int $offset): array
    {
        $line = substr_count(substr($contents, 0, (int) $offset), "\n") + 1;
        $rest = (string) substr($contents, (int) $offset);

        if ($rest === '') {
            return [$line, null];
        }

        $firstLine = explode("\n", $rest, 2)[0];
        $snippet = trim($firstLine);

        return [$line, $snippet === '' ? null : mb_substr($snippet, 0, 200)];
    }
}
