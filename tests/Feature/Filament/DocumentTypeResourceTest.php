<?php

use App\Filament\Resources\DocumentTypes\Pages\ManageDocumentTypes;
use App\Models\DocumentType;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');

    $this->admin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an admin can create a document type', function () {
    Livewire::test(ManageDocumentTypes::class)
        ->callAction('create', data: [
            'name' => 'Employment Contract',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    expect(DocumentType::where('name', 'Employment Contract')->exists())->toBeTrue();
});

test('editing a document type loads its stored values', function () {
    $type = DocumentType::factory()->create(['name' => 'Citizenship Copy']);

    Livewire::test(ManageDocumentTypes::class)
        ->mountAction(TestAction::make('edit')->table($type))
        ->assertActionDataSet(['name' => 'Citizenship Copy']);
});

test('a document type name cannot be reused', function () {
    DocumentType::factory()->create(['name' => 'Certificate']);

    Livewire::test(ManageDocumentTypes::class)
        ->callAction('create', data: [
            'name' => 'Certificate',
            'is_active' => true,
        ])
        ->assertHasActionErrors(['name']);
});
