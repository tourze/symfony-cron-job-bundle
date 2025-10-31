<?php

namespace Tourze\Symfony\CronJob\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[\Attribute(flags: \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsCronTask extends AutoconfigureTag
{
    public const TAG_NAME = 'tourze.job-cron.schedule';

    public function __construct(
        string $expression = '* * * * *',
        ?int $lockTtl = null,
    ) {
        parent::__construct(self::TAG_NAME, [
            'expression' => $expression,
            'lockTtl' => $lockTtl,
        ]);
    }
}
