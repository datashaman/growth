<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class MockupPreviewInspector
{
    /**
     * @return array{visible_text:list<string>,warnings:list<array{code:string,message:string,match:string}>}
     */
    public function inspect(string $html): array
    {
        $payload = $this->render($html, includeScreenshot: false);
        $visibleText = $this->visibleText($payload);

        return [
            'visible_text' => $visibleText,
            'warnings' => $this->warnings($visibleText),
        ];
    }

    /**
     * @return array{content:string,width:int,height:int}
     */
    public function screenshot(string $html): array
    {
        $path = tempnam(sys_get_temp_dir(), 'growth-mockup-preview-');

        if ($path === false) {
            throw new RuntimeException('Mockup preview failed: could not allocate screenshot file.');
        }

        try {
            $payload = $this->render($html, includeScreenshot: true, screenshotPath: $path);
            $content = file_get_contents($path);
        } finally {
            @unlink($path);
        }

        return [
            'content' => $content === false ? '' : $content,
            'width' => (int) data_get($payload, 'screenshot.width', 0),
            'height' => (int) data_get($payload, 'screenshot.height', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function render(string $html, bool $includeScreenshot, ?string $screenshotPath = null): array
    {
        $process = new Process(['node', base_path('scripts/render-mockup-preview.mjs')], base_path());
        $process->setInput(json_encode([
            'html' => $html,
            'include_screenshot' => $includeScreenshot,
            'screenshot_path' => $screenshotPath,
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout(20);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            throw new RuntimeException('Mockup preview failed: '.$exception->getProcess()->getErrorOutput(), previous: $exception);
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new RuntimeException('Mockup preview failed: renderer returned invalid JSON.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function visibleText(array $payload): array
    {
        return array_values(array_filter(
            $payload['visible_text'] ?? [],
            fn ($value): bool => is_string($value) && trim($value) !== '',
        ));
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
                'message' => 'Mockup preview exposes a work item reference.',
            ],
            'requirement_reference' => [
                'pattern' => '/\b(?:REQ|SRS|FR|NFR)-\d{3,}\b/i',
                'message' => 'Mockup preview exposes a requirement reference.',
            ],
            'implementation_note' => [
                'pattern' => '/\b(?:implementation note|internal note|dev note|todo:|fixme:)\b/i',
                'message' => 'Mockup preview exposes an implementation/internal note.',
            ],
            'debug_label' => [
                'pattern' => '/\b(?:debug|debug label|test fixture|placeholder copy)\b/i',
                'message' => 'Mockup preview exposes a debug or fixture label.',
            ],
            'theme_debug_label' => [
                'pattern' => '/\b(?:theme slug|theme debug|theme:\s*[a-z0-9_-]+)\b/i',
                'message' => 'Mockup preview exposes theme/debug metadata.',
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
