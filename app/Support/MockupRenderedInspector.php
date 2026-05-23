<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MockupRenderedInspector
{
    /**
     * @return array{visible_text:list<string>,screenshot:array{mime_type:string,base64:string,width:int,height:int},warnings:list<array{code:string,message:string,match:string}>}
     */
    public function inspect(string $html): array
    {
        $process = new Process(['node', base_path('scripts/render-mockup-inspection.mjs')], base_path());
        $process->setInput(json_encode(['html' => $html], JSON_THROW_ON_ERROR));
        $process->setTimeout(20);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('Rendered mockup inspection failed: '.$exception->getProcess()->getErrorOutput(), previous: $exception);
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Rendered mockup inspection failed: renderer returned invalid JSON.');
        }

        $visibleText = array_values(array_filter(
            $payload['visible_text'] ?? [],
            fn ($value): bool => is_string($value) && trim($value) !== '',
        ));

        return [
            'visible_text' => $visibleText,
            'screenshot' => [
                'mime_type' => 'image/png',
                'base64' => (string) data_get($payload, 'screenshot.base64', ''),
                'width' => (int) data_get($payload, 'screenshot.width', 0),
                'height' => (int) data_get($payload, 'screenshot.height', 0),
            ],
            'warnings' => $this->warnings($visibleText),
        ];
    }

    /**
     * @param  list<string>  $visibleText
     * @return list<array{code:string,message:string,match:string}>
     */
    private function warnings(array $visibleText): array
    {
        $text = implode("\n", $visibleText);
        $rules = [
            'work_item_reference' => [
                'pattern' => '/\bWI-\d{3,}\b/i',
                'message' => 'Rendered mockup exposes a work item reference.',
            ],
            'requirement_reference' => [
                'pattern' => '/\b(?:REQ|SRS|FR|NFR)-\d{3,}\b/i',
                'message' => 'Rendered mockup exposes a requirement reference.',
            ],
            'implementation_note' => [
                'pattern' => '/\b(?:implementation note|internal note|dev note|todo:|fixme:)\b/i',
                'message' => 'Rendered mockup exposes an implementation/internal note.',
            ],
            'debug_label' => [
                'pattern' => '/\b(?:debug|debug label|test fixture|placeholder copy)\b/i',
                'message' => 'Rendered mockup exposes a debug or fixture label.',
            ],
            'theme_debug_label' => [
                'pattern' => '/\b(?:theme slug|theme debug|theme:\s*[a-z0-9_-]+)\b/i',
                'message' => 'Rendered mockup exposes theme/debug metadata.',
            ],
        ];

        $warnings = [];

        foreach ($rules as $code => $rule) {
            if (preg_match($rule['pattern'], $text, $matches) === 1) {
                $warnings[] = [
                    'code' => $code,
                    'message' => $rule['message'],
                    'match' => $matches[0],
                ];
            }
        }

        return $warnings;
    }
}
