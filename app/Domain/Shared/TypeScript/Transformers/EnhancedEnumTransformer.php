<?php

declare(strict_types=1);

namespace App\Domain\Shared\TypeScript\Transformers;

use App\Domain\Shared\Data\Contracts\HasEnumLabels;
use ReflectionEnum;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\References\PhpClassReference;
use Spatie\TypeScriptTransformer\Transformed\Transformed;
use Spatie\TypeScriptTransformer\Transformed\Untransformable;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;

class EnhancedEnumTransformer extends EnumTransformer
{
    public function transform(PhpClassNode $phpClassNode, TransformationContext $context): Transformed|Untransformable
    {
        if (! $phpClassNode->isEnum() || ! $phpClassNode->implementsInterface(HasEnumLabels::class)) {
            return parent::transform($phpClassNode, $context);
        }

        /** @var class-string<\UnitEnum> $className */
        $className = $phpClassNode->getName();
        /** @var ReflectionEnum<\UnitEnum> $enum */
        $enum = new ReflectionEnum($className);
        $enumName = $enum->getShortName();
        $cases = $enum->getCases();

        $enumDefinition = "export enum {$enumName} {\n";

        foreach ($cases as $case) {
            $caseName = $case->getName();
            /** @var \BackedEnum $backedEnum */
            $backedEnum = $case->getValue();
            $caseValue = $backedEnum->value;
            $enumDefinition .= "    {$caseName} = '{$caseValue}',\n";
        }

        $enumDefinition .= "}\n\n";

        $enhancedEnumName = "{$enumName}Options";
        $enumDefinition .= "export const {$enhancedEnumName}: EnumOptions = {\n";

        foreach ($cases as $case) {
            $caseName = $case->getName();
            /** @var \BackedEnum $backedEnum */
            $backedEnum = $case->getValue();
            $caseValue = $backedEnum->value;

            /** @var HasEnumLabels $enumInstance */
            $enumInstance = $case->getValue();
            $label = $enumInstance->label();
            $colorClass = $enumInstance->colorClass();

            $enumDefinition .= "    {$caseName}: {\n";
            $enumDefinition .= "        value: '{$caseValue}',\n";
            $enumDefinition .= "        label: '{$label}',\n";
            $enumDefinition .= "        colorClass: '{$colorClass}',\n";
            $enumDefinition .= "    },\n";
        }

        $enumDefinition .= "};\n";

        return new Transformed(
            new TypeScriptRaw($enumDefinition),
            new PhpClassReference($phpClassNode),
            $context->nameSpaceSegments,
            true,
        );
    }
}
