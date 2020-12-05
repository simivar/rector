<?php

declare(strict_types=1);

namespace Rector\NodeTypeResolver\PHPStan;

use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Rector\PHPStan\Type\AliasedObjectType;
use Rector\PHPStan\Type\ShortenedObjectType;
use Rector\StaticTypeMapper\StaticTypeMapper;
use Rector\TypeDeclaration\TypeNormalizer;

final class TypeComparator
{
    /**
     * @var TypeHasher
     */
    private $typeHasher;

    /**
     * @var TypeNormalizer
     */
    private $typeNormalizer;

    /**
     * @var StaticTypeMapper
     */
    private $staticTypeMapper;

    public function __construct(
        TypeHasher $typeHasher,
        TypeNormalizer $typeNormalizer,
        StaticTypeMapper $staticTypeMapper
    ) {
        $this->typeHasher = $typeHasher;
        $this->typeNormalizer = $typeNormalizer;
        $this->staticTypeMapper = $staticTypeMapper;
    }

    public function areTypesEqual(Type $firstType, Type $secondType): bool
    {
        if ($this->areBothSameScalarType($firstType, $secondType)) {
            return true;
        }

        // aliases and types
        if ($this->areAliasedObjectMatchingFqnObject($firstType, $secondType)) {
            return true;
        }

        $firstType = $this->typeNormalizer->normalizeArrayOfUnionToUnionArray($firstType);
        $secondType = $this->typeNormalizer->normalizeArrayOfUnionToUnionArray($secondType);

        if ($this->typeHasher->areTypesEqual($firstType, $secondType)) {
            return true;
        }

        return $this->areArrayTypeWithSingleObjectChildToParent($firstType, $secondType);
    }

    public function arePhpParserAndPhpStanPhpDocTypesEqual(
        Node $phpParserNode,
        TypeNode $phpStanDocTypeNode,
        Node $node
    ): bool {
        $phpParserNodeType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($phpParserNode);
        $phpStanDocType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType(
            $phpStanDocTypeNode,
            $node
        );

        return $this->areTypesEqual($phpParserNodeType, $phpStanDocType);
    }

    private function areBothSameScalarType(Type $firstType, Type $secondType): bool
    {
        if ($firstType instanceof StringType && $secondType instanceof StringType) {
            // prevents "class-string" vs "string"
            return get_class($firstType) === get_class($secondType);
        }

        if ($firstType instanceof IntegerType && $secondType instanceof IntegerType) {
            return true;
        }

        if ($firstType instanceof FloatType && $secondType instanceof FloatType) {
            return true;
        }
        return $firstType instanceof BooleanType && $secondType instanceof BooleanType;
    }

    private function areAliasedObjectMatchingFqnObject(Type $firstType, Type $secondType): bool
    {
        if ($firstType instanceof AliasedObjectType && $secondType instanceof ObjectType && $firstType->getFullyQualifiedClass() === $secondType->getClassName()) {
            return true;
        }
        return $secondType instanceof AliasedObjectType && $firstType instanceof ObjectType && $secondType->getFullyQualifiedClass() === $firstType->getClassName();
    }

    /**
     * E.g. class A extends B, class B → A[] is subtype of B[] → keep A[]
     */
    private function areArrayTypeWithSingleObjectChildToParent(Type $firstType, Type $secondType): bool
    {
        if (! $firstType instanceof ArrayType || ! $secondType instanceof ArrayType) {
            return false;
        }

        $firstArrayItemType = $firstType->getItemType();
        $secondArrayItemType = $secondType->getItemType();

        if ($firstArrayItemType instanceof ObjectType && $secondArrayItemType instanceof ObjectType) {
            $firstFqnClassName = $this->getFqnClassName($firstArrayItemType);
            $secondFqnClassName = $this->getFqnClassName($secondArrayItemType);

            if (is_a($firstFqnClassName, $secondFqnClassName, true)) {
                return true;
            }

            if (is_a($secondFqnClassName, $firstFqnClassName, true)) {
                return true;
            }
        }

        return false;
    }

    private function getFqnClassName(ObjectType $objectType): string
    {
        if ($objectType instanceof ShortenedObjectType) {
            return $objectType->getFullyQualifiedName();
        }

        return $objectType->getClassName();
    }
}
