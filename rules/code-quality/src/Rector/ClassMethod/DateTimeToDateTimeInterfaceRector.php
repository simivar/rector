<?php

declare(strict_types=1);

namespace Rector\CodeQuality\Rector\ClassMethod;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\NullType as PHPStanNullType;
use PHPStan\Type\ObjectType as PHPStanObjectType;
use PHPStan\Type\UnionType as PHPStanUnionType;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;

/**
 * @see \Rector\CodeQuality\Tests\Rector\ClassMethod\DateTimeToDateTimeInterfaceRector\DateTimeToDateTimeInterfaceRectorTest
 */
final class DateTimeToDateTimeInterfaceRector extends AbstractRector
{
    private const METHODS_MAP = [
        'add', 'modify', '__set_state', 'setDate', 'setISODate', 'setTime', 'setTimestamp', 'setTimezone', 'sub',
    ];

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    public function __construct(NodeTypeResolver $nodeTypeResolver)
    {
        $this->nodeTypeResolver = $nodeTypeResolver;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Changes DateTime type-hint to DateTimeInterface', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass {
    public function methodWithDateTime(\DateTime $dateTime)
    {
        return true;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass {
    /**
     * @param \DateTime|\DateTimeImmutable $dateTime
     */
    public function methodWithDateTime(\DateTimeInterface $dateTime)
    {
        return true;
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $isModifiedNode = false;
        foreach ($node->getParams() as $param) {
            if (! $this->isDateTimeParam($param)) {
                continue;
            }

            $this->refactorParamTypeHint($param);
            $this->refactorParamDocBlock($param, $node);
            $this->refactorMethodCalls($param, $node);
            $isModifiedNode = true;
        }

        if (! $isModifiedNode) {
            return null;
        }

        return $node;
    }

    private function refactorParamTypeHint(Param $param): void
    {
        $dateTimeInterfaceType = new FullyQualified(DateTimeInterface::class);
        if ($param->type instanceof NullableType) {
            $param->type = new NullableType($dateTimeInterfaceType);
            return;
        }

        $param->type = $dateTimeInterfaceType;
    }

    private function refactorParamDocBlock(Param $param, ClassMethod $classMethod): void
    {
        /** @var PhpDocInfo|null $phpDocInfo */
        $phpDocInfo = $classMethod->getAttribute(AttributeKey::PHP_DOC_INFO);
        if ($phpDocInfo === null) {
            $phpDocInfo = $this->phpDocInfoFactory->createEmpty($classMethod);
        }

        $types = [new PHPStanObjectType(DateTime::class), new PHPStanObjectType(DateTimeImmutable::class)];
        if ($param->type instanceof NullableType) {
            $types[] = new PHPStanNullType();
        }

        $paramName = $this->getName($param->var);
        if ($paramName === null) {
            throw new ShouldNotHappenException();
        }
        $phpDocInfo->changeParamType(new PHPStanUnionType($types), $param, $paramName);
    }

    private function refactorMethodCalls(Param $param, ClassMethod $classMethod): void
    {
        $this->traverseNodesWithCallable($classMethod->stmts, function (Node $node) use ($param) {
            if (!($node instanceof MethodCall)){
                return;
            }

            $this->refactorMethodCall($param, $node);
        });
    }

    private function refactorMethodCall(Param $param, MethodCall $methodCall): void
    {
        $paramName = $this->getName($param->var);
        if ($this->shouldSkipMethodCallRefactor($paramName, $methodCall)) {
            return;
        }

        $newAssignNode = new Assign(new Variable($paramName), $methodCall);

        $parentNode = $methodCall->getAttribute(AttributeKey::PARENT_NODE);
        if ($parentNode instanceof Arg) {
            $parentNode->value = $newAssignNode;
            return;
        }

        $parentNode->expr = $newAssignNode;
    }

    private function shouldSkipMethodCallRefactor(string $paramName, MethodCall $methodCall): bool
    {
        if (! $this->isName($methodCall->var, $paramName)) {
            return true;
        }

        if (! in_array($this->getName($methodCall->name), self::METHODS_MAP)) {
            return true;
        }

        if ($methodCall->getAttribute(AttributeKey::PARENT_NODE) === null) {
            return true;
        }

        $parentNode = $methodCall->getAttribute(AttributeKey::PARENT_NODE);
        return $parentNode instanceof Assign;
    }

    private function isDateTimeParam(Param $param): bool
    {
        return $this->nodeTypeResolver->isObjectTypeOrNullableObjectType($param, DateTime::class);
    }
}