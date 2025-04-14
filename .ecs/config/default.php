<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;

return static function (ECSConfig $ECSConfig): void {
    // Contao
    $ECSConfig->import(__DIR__.'../../../../../contao/easy-coding-standard/config/contao.php');

    $ECSConfig->skip([
        MethodChainingIndentationFixer::class => [
            'DependencyInjection/Configuration.php',
        ],
    ]);

    // Custom
    $ECSConfig->import(__DIR__.'/set/header_comment_fixer.php');
};
