<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type_id' => DocumentType::factory(),
            'path' => 'employee-documents/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->word().'.pdf',
            'notes' => null,
            'uploaded_by' => User::factory(),
        ];
    }
}
