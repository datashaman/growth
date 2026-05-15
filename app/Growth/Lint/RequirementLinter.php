<?php

namespace App\Growth\Lint;

use App\Models\Requirement;

/**
 * Requirement quality checks.
 *
 * Each finding: array{rule, severity, message, subject_type, subject_id}.
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
     * @return list<array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}>
     */
    public function check(Requirement $requirement): array
    {
        $findings = [];
        $text = $requirement->text;

        foreach (self::PHRASE_RULES as [$rule, $pattern, $message]) {
            if (preg_match($pattern, $text, $matches)) {
                $findings[] = $this->finding($requirement, $rule, 'warning',
                    $message.' (matched: "'.$matches[0].'")');
            }
        }

        if (preg_match('/\b(TBD|TBS|TBR)\b/', $text)) {
            $findings[] = $this->finding($requirement, 'incomplete', 'error',
                'rule: requirement contains TBD/TBS/TBR — not complete');
        }

        if (preg_match('/\b(and|or)\b.*\bshall\b/i', $text)
            && substr_count(strtolower($text), 'shall') > 1) {
            $findings[] = $this->finding($requirement, 'singular', 'warning',
                'rule: multiple "shall" clauses — split into singular requirements');
        }

        if (! preg_match('/\b(shall|must|will)\b/i', $text)) {
            $findings[] = $this->finding($requirement, 'mandate-verb', 'warning',
                'rule: requirement lacks a mandate verb (shall/must/will)');
        }

        $criteria = array_values(array_filter($requirement->acceptance_criteria ?? []));

        if ($criteria === [] && ($requirement->priority === 'high' || $requirement->project?->rigor_level >= 3)) {
            $findings[] = $this->finding($requirement, 'acceptance-criteria-missing', 'warning',
                'High-priority or high-integrity requirements should define concrete acceptance criteria');
        }

        foreach ($criteria as $criterion) {
            if (preg_match('/\b(TBD|TBS|TBR)\b/', $criterion)) {
                $findings[] = $this->finding($requirement, 'acceptance-criteria-incomplete', 'error',
                    'Acceptance criterion contains TBD/TBS/TBR and is not verifiable');
            }
        }

        return $findings;
    }

    /**
     * @return array{rule:string,severity:string,message:string,subject_type:string,subject_id:string}
     */
    private function finding(Requirement $requirement, string $rule, string $severity, string $message): array
    {
        return [
            'rule' => $rule,
            'severity' => $severity,
            'message' => $message,
            'subject_type' => 'requirement',
            'subject_id' => $requirement->id,
        ];
    }
}
