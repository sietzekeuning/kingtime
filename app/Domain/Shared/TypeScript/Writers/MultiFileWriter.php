<?php

declare(strict_types=1);

namespace App\Domain\Shared\TypeScript\Writers;

use Spatie\TypeScriptTransformer\Actions\ResolveImportsAndResolvedReferenceMapAction;
use Spatie\TypeScriptTransformer\Collections\TransformedCollection;
use Spatie\TypeScriptTransformer\Data\GlobalNamespaceResolvedReference;
use Spatie\TypeScriptTransformer\Data\ModuleImportResolvedReference;
use Spatie\TypeScriptTransformer\Data\WriteableFile;
use Spatie\TypeScriptTransformer\Data\WritingContext;
use Spatie\TypeScriptTransformer\References\ClassStringReference;
use Spatie\TypeScriptTransformer\References\PhpClassReference;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Writers\Writer;

class MultiFileWriter implements Writer
{
    protected ResolveImportsAndResolvedReferenceMapAction $resolveImportsAction;

    public function __construct(
        protected string $typesPath = 'types/generated.d.ts',
        protected string $enumsPath = 'lib/enums.ts',
    ) {
        $this->resolveImportsAction = new ResolveImportsAndResolvedReferenceMapAction;
    }

    /**
     * @param  array<Transformed>  $transformed
     * @return array<WriteableFile>
     */
    public function output(array $transformed, TransformedCollection $transformedCollection): array
    {
        $enumTypes = [];
        $dataTypes = [];

        foreach ($transformed as $item) {
            $ref = $item->getReference();

            $isEnum =
                $ref instanceof PhpClassReference && $ref->phpClassNode->isEnum()
                || $ref instanceof ClassStringReference && enum_exists($ref->getKey());

            if ($isEnum) {
                $enumTypes[] = $item;
            } else {
                $dataTypes[] = $item;
            }
        }

        $files = [];

        if (! empty($enumTypes)) {
            $files[] = $this->writeEnumsFile($enumTypes, $transformedCollection);
        }

        if (! empty($dataTypes)) {
            $files[] = $this->writeTypesFile($dataTypes, $transformedCollection);
        }

        return $files;
    }

    public function resolveReference(Transformed $transformed): ModuleImportResolvedReference|GlobalNamespaceResolvedReference
    {
        $name = $transformed->getName() ?? class_basename($transformed->getReference()->getKey());

        return new ModuleImportResolvedReference($name, $this->typesPath);
    }

    /**
     * @param  array<Transformed>  $enumTypes
     */
    protected function writeEnumsFile(array $enumTypes, TransformedCollection $transformedCollection): WriteableFile
    {
        [, $resolvedReferenceMap] = $this->resolveImportsAction->execute(
            $this->enumsPath,
            $enumTypes,
            $transformedCollection,
        );

        $output = "// Auto-generated TypeScript enums\n\n";
        $output .= "export type EnumOption = {\n";
        $output .= "    value: string\n";
        $output .= "    label: string\n";
        $output .= "    colorClass: string\n";
        $output .= "}\n\n";
        $output .= "export type EnumOptions = Record<string, EnumOption>\n\n";

        $writingContext = new WritingContext($resolvedReferenceMap);
        $processedEnums = [];

        foreach ($enumTypes as $item) {
            $key = $item->getName() ?? $item->getReference()->getKey();

            if (in_array($key, $processedEnums, true)) {
                continue;
            }

            $processedEnums[] = $key;

            $written = $item->write($writingContext);

            if (str_contains($written, 'enum') || str_contains($written, 'export const')) {
                $output .= $written."\n";
            }
        }

        return new WriteableFile($this->enumsPath, $output);
    }

    /**
     * @param  array<Transformed>  $dataTypes
     */
    protected function writeTypesFile(array $dataTypes, TransformedCollection $transformedCollection): WriteableFile
    {
        [$imports, $resolvedReferenceMap] = $this->resolveImportsAction->execute(
            $this->typesPath,
            $dataTypes,
            $transformedCollection,
        );

        $writingContext = new WritingContext($resolvedReferenceMap);
        $output = '';

        foreach ($imports->getTypeScriptNodes() as $import) {
            $output .= $import->write($writingContext)."\n";
        }

        foreach ($dataTypes as $item) {
            $output .= $item->write($writingContext)."\n";
        }

        return new WriteableFile($this->typesPath, $output);
    }
}
