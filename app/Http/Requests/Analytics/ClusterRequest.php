<?php

// app/Http/Requests/Analytics/ClusterRequest.php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class ClusterRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id'  => ['required', 'uuid'],
      'radius'      => ['required', 'numeric', 'min:1', 'max:100000'],
      'min_points'  => ['nullable', 'integer', 'min:1', 'max:100'],
    ];
  }
}
