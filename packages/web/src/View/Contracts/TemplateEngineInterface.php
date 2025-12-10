<?php

declare(strict_types=1);

namespace Lalaz\Web\View\Contracts;

interface TemplateEngineInterface
{
    public function render(string $template, array $data = []): string;
    public function renderFromString(string $content, array $data = []): string;
}
