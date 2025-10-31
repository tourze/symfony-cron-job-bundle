<?php

declare(strict_types=1);

namespace Tourze\Symfony\CronJob\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

/**
 * @implements Rule<Class_>
 */
class RequireAsCommandAttributeRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Class_);

        if ($node->isAbstract() || $node->isAnonymous()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        try {
            if (!$this->hasAsCronTaskAttribute($classReflection)) {
                return [];
            }

            if (!$this->hasAsCommandAttribute($classReflection)) {
                return [
                    RuleErrorBuilder::message(
                        sprintf(
                            '使用 %s 注解的类必须同时使用 %s 注解',
                            AsCronTask::class,
                            AsCommand::class
                        )
                    )
                        ->identifier('cronTask.requireAsCommand')
                        ->tip(sprintf('请为类 %s 添加 #[AsCommand] 注解', $classReflection->getName()))
                        ->build(),
                ];
            }
        } catch (\ReflectionException) {
            return [];
        }

        return [];
    }

    private function hasAsCronTaskAttribute(ClassReflection $classReflection): bool
    {
        try {
            $reflectionClass = $classReflection->getNativeReflection();
            $attributes = $reflectionClass->getAttributes(AsCronTask::class);

            return count($attributes) > 0;
        } catch (\ReflectionException) {
            return false;
        }
    }

    private function hasAsCommandAttribute(ClassReflection $classReflection): bool
    {
        try {
            $reflectionClass = $classReflection->getNativeReflection();
            $attributes = $reflectionClass->getAttributes(AsCommand::class);

            return count($attributes) > 0;
        } catch (\ReflectionException) {
            return false;
        }
    }
}
