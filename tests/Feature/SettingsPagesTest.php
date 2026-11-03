<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Every editable settings page opens, and each of its forms saves again with the values it shows. */
class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return collect([
            'settings.display', 'settings.codes', 'settings.documents', 'settings.rules', 'settings.process-setup.edit', 'settings.process',
            'settings.melting-days.edit', 'settings.quotation', 'settings.size.edit', 'settings.modules.edit', 'finance.settings', 'finance.tax-year',
            'ledger.settings', 'ledger.settings.sales-documents', 'ledger.settings.posting-rules', 'custom-fields.index',
        ])->mapWithKeys(fn (string $route) => [$route => [$route]])->all();
    }

    /** @dataProvider pages */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_opens_and_its_forms_save_with_the_values_they_show(string $route): void
    {
        $html = $this->get(route($route))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new \DOMXPath($dom);
        $problems = [];

        foreach ($xpath->query('//form') as $form) {
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            $spoof = $xpath->query('.//input[@name="_method"]', $form)->item(0)?->getAttribute('value');
            $verb = strtoupper($spoof ?: $method);
            $action = $form->getAttribute('action');
            if ($verb === 'GET' || $action === '' || str_contains($action, 'logout') || ! str_contains($action, '/')) {
                continue;
            }
            $data = [];
            $radios = [];
            foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $field) {
                $name = $field->getAttribute('name');
                if ($name === '' || $name === '_token' || $name === '_method' || $field->hasAttribute('disabled')) {
                    continue;
                }
                $type = strtolower($field->getAttribute('type'));
                if ($field->nodeName === 'select') {
                    $selected = $xpath->query('.//option[@selected]', $field)->item(0) ?? $xpath->query('.//option', $field)->item(0);
                    $value = $selected?->getAttribute('value') ?? '';
                } elseif ($field->nodeName === 'textarea') {
                    $value = $field->textContent;
                } elseif ($type === 'radio') {
                    $radios[$name] ??= false;
                    if (! $field->hasAttribute('checked')) {
                        continue;
                    }
                    $radios[$name] = true;
                    $value = $field->getAttribute('value');
                } elseif ($type === 'checkbox') {
                    if (! $field->hasAttribute('checked')) {
                        continue;
                    }
                    $value = $field->getAttribute('value') ?: 'on';
                } elseif (in_array($type, ['file', 'submit', 'button'], true)) {
                    continue;
                } else {
                    $value = $field->getAttribute('value');
                }
                Str::endsWith($name, '[]') ? $data[substr($name, 0, -2)][] = $value : data_set($data, str_replace(['[', ']'], ['.', ''], $name), $value);
            }
            foreach ($radios as $name => $ticked) {
                if (! $ticked) {
                    $problems[] = "$verb $action: nothing is ticked for \"$name\"";
                }
            }
            $response = $this->call($verb, $action, $data);
            if ($response->getStatusCode() >= 400) {
                $problems[] = "$verb $action: status ".$response->getStatusCode();
            } elseif (($bag = session('errors')) instanceof \Illuminate\Support\ViewErrorBag && $bag->any()) {
                $problems[] = "$verb $action: ".implode(' | ', $bag->getBag('default')->all());
            }
            session()->flush();
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
