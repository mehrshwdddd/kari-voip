<?php

namespace App\Services\Messaging;

final class MessageTemplateEngine
{
    /**
     * Extract all variable names enclosed in {var} or {{var}} from a template body.
     *
     * @return array<int, string>
     */
    public function extractVariables(string $body): array
    {
        preg_match_all('/\{\{?\s*([a-zA-Z0-9_]+)\s*\}?\}/', $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Render a template string by replacing {var} and {{var}} placeholders with provided values.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function render(string $body, array $variables = []): string
    {
        $rendered = $body;

        foreach ($variables as $key => $value) {
            $strVal = (string) ($value ?? '');
            $rendered = preg_replace('/\{\{?\s*'.preg_quote((string) $key, '/').'\s*\}?\}/', $strVal, $rendered) ?? $rendered;
        }

        return $rendered;
    }
}
