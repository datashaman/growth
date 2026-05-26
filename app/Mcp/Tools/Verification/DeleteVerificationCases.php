<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestCase as TestCaseModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete verification cases by filter. Currently supports id=[...] for up to 100 verification case ULIDs. Each deleted case also deletes its runs and unlinks requirements.')]
class DeleteVerificationCases extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|array|min:1|max:100',
            'id.*' => 'required|string|distinct|owned_test_case',
        ], [
            'id.max' => 'Batches are capped at 100 ids per call. Split into smaller batches.',
        ]);

        $cases = TestCaseModel::whereIn('id', $data['id'])->get()->keyBy('id');

        $deleted = [];
        foreach ($data['id'] as $id) {
            /** @var TestCaseModel $case */
            $case = $cases->get($id);
            $runs = $case->runs()->count();
            $requirements = $case->requirements()->count();
            $case->delete();

            $deleted[] = [
                'id' => $id,
                'deleted' => true,
                'runs_deleted' => $runs,
                'requirements_unlinked' => $requirements,
            ];
        }

        return Response::structured([
            'filters' => ['id' => $data['id']],
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(100)
                ->description('Verification case ULIDs to delete. This is the first supported delete filter: id=[...].')
                ->required(),
        ];
    }
}
