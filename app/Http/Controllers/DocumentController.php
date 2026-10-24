<?php

namespace App\Http\Controllers;

use App\Services\Papers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function store(Request $request, Papers $papers): RedirectResponse
    {
        $papers->add($this->payload($request));

        return back()->with('success', 'Document added.');
    }

    public function update(Request $request, string $document, Papers $papers): RedirectResponse
    {
        $papers->update($document, $this->payload($request));

        return back()->with('success', 'Document saved.');
    }

    public function destroy(string $document, Papers $papers): RedirectResponse
    {
        $papers->remove($document);

        return back()->with('success', 'Document removed.');
    }

    /**
     * @return array{kind: string, name: string, issued_on: ?string, expires_on: ?string, renewal_months: ?int, notes: ?string}
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['license', 'registration', 'insurance', 'other'])],
            'name' => ['required', 'string', 'max:80'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'renewal_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'kind' => $data['kind'],
            'name' => $data['name'],
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'renewal_months' => isset($data['renewal_months']) ? (int) $data['renewal_months'] : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
