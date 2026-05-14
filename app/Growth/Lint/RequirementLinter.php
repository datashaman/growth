<?php

namespace App\Growth\Lint;

use App\Models\Requirement;

/**
 * Requirement quality checks.
 *
 * Each finding is an array with: rule, severity, message.
 * Severities: error, warning.
 */
class RequirementLinter
{
    /**
     * Banned phrase rules. Each entry: [rule_id, regex, message].
     */
    private const PHRASE_RULES = [
        ['superlative',  '/\b(best|most|optimal|maximum possible|minimum possible)\b/i',
            'rule: superlatives produce non-verifiable requirements'],
        ['subjective',   '/\b(user[- ]?friendly|easy to use|cost[- ]?effective|intuitive|seamless)\b/i',
            'rule: subjective language is not verifiable'],
        ['vague-adverb', '/\b(almost always|significantly|minimally|approximately|roughly|sufficient|adequate)\b/i',
            'rule: ambiguous adverb/adjective'],
        ['open-ended',   '/\b(but not limited to|as a minimum|as needed|provide support for|including but)\b/i',
            'rule: open-ended phrase leaves scope unbounded'],
        ['comparative',  '/\b(better than|higher quality|faster than|more reliable than)\b/i',
            'rule: comparative without a quantified baseline'],
        ['loophole',     '/\b(if possible|as appropriate|as applicable|where feasible|if practical)\b/i',
            'rule: loophole phrase undermines verifiability'],
        ['etc-vague',    '/\b(etc\.?|and so on|and so forth)\b/i',
            'rule: open enumeration leaves scope unbounded'],
        ['pronoun',      '/\b(we|our|us|your)\b/i',
            'rule: stakeholder pronouns belong in concerns, not requirements'],
        ['and-or',       '/\band\/or\b/i',
            'rule: "and/or" is ambiguous — pick one'],
        ['future-tense', '/\b(will be|is going to|are going to)\b/i',
            'rule: future tense weakens the mandate — use present-tense "shall"'],
        ['bare-support', '/\bshall (?:support|handle|manage|deal with)\b(?!\s+(?:up to|at least|at most|exactly|the following|all of))/i',
            'rule: "shall support/handle" without measurable scope is not verifiable'],
    ];

    /**
     * @return list<array{rule:string,severity:string,message:string}>
     */
    public function check(Requirement $requirement): array
    {
        $findings = [];
        $text = $requirement->text;

        foreach (self::PHRASE_RULES as [$rule, $pattern, $message]) {
            if (preg_match($pattern, $text, $matches)) {
                $findings[] = [
                    'rule' => $rule,
                    'severity' => 'warning',
                    'message' => $message.' (matched: "'.$matches[0].'")',
                ];
            }
        }

        if (preg_match('/\b(TBD|TBS|TBR)\b/', $text)) {
            $findings[] = [
                'rule' => 'incomplete',
                'severity' => 'error',
                'message' => 'rule: requirement contains TBD/TBS/TBR — not complete',
            ];
        }

        if (preg_match('/\b(and|or)\b.*\bshall\b/i', $text)
            && substr_count(strtolower($text), 'shall') > 1) {
            $findings[] = [
                'rule' => 'singular',
                'severity' => 'warning',
                'message' => 'rule: multiple "shall" clauses — split into singular requirements',
            ];
        }

        if (! preg_match('/\b(shall|must|will)\b/i', $text)) {
            $findings[] = [
                'rule' => 'mandate-verb',
                'severity' => 'warning',
                'message' => 'rule: requirement lacks a mandate verb (shall/must/will)',
            ];
        }

        $criteria = array_values(array_filter($requirement->acceptance_criteria ?? []));

        if ($criteria === [] && ($requirement->priority === 'high' || $requirement->project?->rigor_level >= 3)) {
            $findings[] = [
                'rule' => 'acceptance-criteria-missing',
                'severity' => 'warning',
                'message' => 'High-priority or high-integrity requirements should define concrete acceptance criteria',
            ];
        }

        foreach ($criteria as $criterion) {
            if (preg_match('/\b(TBD|TBS|TBR)\b/', $criterion)) {
                $findings[] = [
                    'rule' => 'acceptance-criteria-incomplete',
                    'severity' => 'error',
                    'message' => 'Acceptance criterion contains TBD/TBS/TBR and is not verifiable',
                ];
            }
        }

        return $findings;
    }
}
