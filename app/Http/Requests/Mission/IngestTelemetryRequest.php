<?php

// app/Http/Requests/Mission/IngestTelemetryRequest.php

namespace App\Http\Requests\Mission;

use Illuminate\Foundation\Http\FormRequest;

class IngestTelemetryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    // Supports both single ping and batch array of pings
    if ($this->has('pings')) {
      return $this->batchRules();
    }

    return $this->singleRules();
  }

  private function singleRules(): array
  {
    return [
      'recorded_at' => ['required', 'date'],
      'lat'         => ['required', 'numeric', 'between:-90,90'],
      'lng'         => ['required', 'numeric', 'between:-180,180'],
      'altitude'    => ['nullable', 'numeric'],
      'speed'       => ['nullable', 'numeric', 'min:0'],
      'heading'     => ['nullable', 'numeric', 'between:0,360'],
      'metadata'    => ['nullable', 'array'],
    ];
  }

  private function batchRules(): array
  {
    return [
      'pings'                => ['required', 'array', 'min:1'],
      'pings.*.recorded_at'  => ['required', 'date'],
      'pings.*.lat'          => ['required', 'numeric', 'between:-90,90'],
      'pings.*.lng'          => ['required', 'numeric', 'between:-180,180'],
      'pings.*.altitude'     => ['nullable', 'numeric'],
      'pings.*.speed'        => ['nullable', 'numeric', 'min:0'],
      'pings.*.heading'      => ['nullable', 'numeric', 'between:0,360'],
    ];
  }
}
