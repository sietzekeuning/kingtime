<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shared\TypeScript\Transformers\EnhancedEnumTransformer;
use App\Domain\Shared\TypeScript\Writers\MultiFileWriter;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;

class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnhancedEnumTransformer::class)
            ->transformDirectories(app_path('Domain'))
            ->outputDirectory(resource_path('js'))
            ->writer(new MultiFileWriter(typesPath: 'types/generated.d.ts', enumsPath: 'lib/enums.ts'));
    }
}
