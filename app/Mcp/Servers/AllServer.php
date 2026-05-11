<?php

namespace App\Mcp\Servers;

use App\Mcp\Servers\Concerns\RoleServerDefaults;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

#[Name('All Server')]
#[Version('0.1.0')]
#[Instructions('Expose the complete MCP surface for power users and integration checks.')]
class AllServer extends Server
{
    use RoleServerDefaults {
        boot as bootRoleServerDefaults;
    }

    protected function boot(): void
    {
        $this->tools = $this->discoverPrimitives(app_path('Mcp/Tools'), Tool::class);
        $this->resources = $this->discoverPrimitives(app_path('Mcp/Resources'), Resource::class);
        $this->prompts = $this->discoverPrimitives(app_path('Mcp/Prompts'), Prompt::class);

        $this->bootRoleServerDefaults();
    }

    /**
     * @template T of Primitive
     *
     * @param  class-string<T>  $baseClass
     * @return array<int, class-string<T>>
     */
    private function discoverPrimitives(string $path, string $baseClass): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        return collect(File::allFiles($path))
            ->map(fn (SplFileInfo $file): string => $this->classNameFor($file))
            ->filter(fn (string $class): bool => class_exists($class))
            ->filter(function (string $class) use ($baseClass): bool {
                if (! is_subclass_of($class, $baseClass)) {
                    return false;
                }

                return ! (new ReflectionClass($class))->isAbstract();
            })
            ->sort()
            ->values()
            ->all();
    }

    private function classNameFor(SplFileInfo $file): string
    {
        return 'App\\'.Str::of($file->getPathname())
            ->after(app_path().DIRECTORY_SEPARATOR)
            ->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\');
    }
}
