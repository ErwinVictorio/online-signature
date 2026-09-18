<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlacementValidator
{
    public function validate(array $data, int $userId, int $pageCount, bool $required = false): array
    {
        $validated = Validator::make($data, [
            'placements' => ['present', 'array', 'max:200', $required ? 'min:1' : 'min:0'],
            'placements.*' => 'array:id,type,signature_id,page_number,x_ratio,y_ratio,width_ratio,height_ratio,text',
            'placements.*.id' => 'required|uuid|distinct',
            'placements.*.type' => ['required', Rule::in(['signature', 'initials', 'name', 'date'])],
            'placements.*.signature_id' => ['nullable', 'integer', Rule::exists('signatures', 'id')->where('user_id', $userId)],
            'placements.*.page_number' => 'required|integer|min:1|max:'.$pageCount,
            'placements.*.x_ratio' => 'required|numeric|between:0,1',
            'placements.*.y_ratio' => 'required|numeric|between:0,1',
            'placements.*.width_ratio' => 'required|numeric|between:0.005,1',
            'placements.*.height_ratio' => 'required|numeric|between:0.005,1',
            'placements.*.text' => 'nullable|string|max:200',
        ])->validate();
        foreach ($validated['placements'] as $index => $placement) {
            $error = null;
            if ($placement['x_ratio'] + $placement['width_ratio'] > 1.000001 || $placement['y_ratio'] + $placement['height_ratio'] > 1.000001) {
                $error = 'Keep every element within the page.';
            }
            if ($placement['type'] === 'signature' && empty($placement['signature_id'])) {
                $error = 'Choose an available signature.';
            }
            if ($placement['type'] !== 'signature' && (empty(trim($placement['text'] ?? '')) || ! empty($placement['signature_id']))) {
                $error = 'Text elements need text and cannot reference a signature.';
            }
            if ($error) {
                throw ValidationException::withMessages(['placements.'.$index => $error]);
            }
        }

        return $validated['placements'];
    }
}
