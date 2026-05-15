<?php

namespace App\Growth\Manifest;

use RuntimeException;

/**
 * Produces apply-manifest-ready starter manifests per rigor level.
 *
 * Each template provides the minimum structure to satisfy that rigor's
 * lint rules where the manifest schema covers them. Events (plan
 * baselines, review records) live outside the manifest by design;
 * agents must follow up with `baseline-plan` and `upsert-review`
 * after applying an L3+ template.
 *
 * String values that read like instructions ("TODO: …") are intentional
 * placeholders for the consuming agent to replace before applying.
 */
class StarterTemplates
{
    public function template(int $rigor): array
    {
        if ($rigor < 1 || $rigor > 4) {
            throw new RuntimeException("Rigor level must be between 1 and 4 (got {$rigor}).");
        }

        $manifest = [
            'project' => [
                'name' => 'TODO: project name',
                'description' => 'TODO: one or two sentences describing what this project delivers.',
                'rigor_level' => $rigor,
                'status' => 'draft',
            ],
            'stakeholders' => [
                [
                    'slug' => 'primary-user',
                    'name' => 'Primary User',
                    'kind' => 'class',
                    'role' => 'primary stakeholder',
                    'description' => 'TODO: describe the user role this project serves.',
                ],
            ],
            'concerns' => [
                [
                    'slug' => 'primary-purpose',
                    'text' => 'The product fulfills its primary purpose reliably under expected load.',
                    'raised_by' => 'primary-user',
                ],
            ],
            'requirements' => [
                [
                    'slug' => 'primary-action',
                    'type' => 'functional',
                    'text' => 'The system shall provide its primary action when the user invokes it.',
                    'acceptance_criteria' => [
                        'Invoking the primary action produces the expected outcome.',
                        'The action records an audit entry with the actor and timestamp.',
                    ],
                ],
            ],
            'architecture' => [
                'views' => [
                    [
                        'slug' => 'context',
                        'name' => 'System Context',
                        'viewpoint' => 'context',
                        'addresses_concerns' => ['primary-purpose'],
                        'elements' => [
                            [
                                'slug' => 'system',
                                'kind' => 'entity',
                                'name' => 'System under design',
                            ],
                        ],
                    ],
                ],
            ],
            'plan' => [
                'status' => 'draft',
                'scope_summary' => 'TODO: list in-scope and out-of-scope items.',
                'approach' => 'TODO: describe the delivery approach (iterative, phased, etc.).',
            ],
            'verification' => [
                'plans' => [
                    [
                        'slug' => 'unit-verification',
                        'name' => 'Unit verification',
                        'level' => 'unit',
                        'scope' => 'TODO: scope of unit-level verification.',
                        'approach' => 'TODO: how unit verification is executed.',
                        'cases' => [
                            [
                                'slug' => 'primary-action-case',
                                'name' => 'Primary action produces the expected outcome',
                                'expected_results' => 'Invoking the primary action produces the expected outcome and writes an audit entry.',
                                'verifies_requirements' => ['primary-action'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($rigor >= 2) {
            $manifest['plan']['milestones'] = [
                [
                    'slug' => 'first-milestone',
                    'name' => 'First milestone',
                    'status' => 'pending',
                ],
            ];
            $manifest['plan']['work_items'] = [
                [
                    'slug' => 'deliver-primary-action',
                    'kind' => 'deliverable',
                    'name' => 'Deliver primary action',
                    'status' => 'todo',
                    'requirements' => ['primary-action'],
                    'milestones' => ['first-milestone'],
                ],
            ];
        }

        if ($rigor >= 3) {
            $manifest['plan']['roles'] = [
                [
                    'slug' => 'project-lead',
                    'name' => 'Project Lead',
                    'responsibilities' => 'TODO: describe what this role owns end-to-end.',
                ],
            ];
            $manifest['plan']['work_items'][0]['responsible_role'] = 'project-lead';
        }

        return $manifest;
    }
}
