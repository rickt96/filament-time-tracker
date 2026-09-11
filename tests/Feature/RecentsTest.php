<?php

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Widgets\RecentsWidget;
use App\Models\PageVisit;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('opening a record page upserts one visit row for the current user', function () {
    $project = makeProject(['name' => 'Progetto tracciato']);
    $workspace = $project->workspace;
    $user = User::factory()->create();
    $workspace->users()->attach($user);

    $this->actingAs($user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($workspace);

    Livewire::test(EditProject::class, ['record' => $project->getKey()]);
    $first = $user->pageVisits()->sole()->last_opened_at;

    $this->travel(1)->minutes();
    Livewire::test(EditProject::class, ['record' => $project->getKey()]);

    expect(PageVisit::count())->toBe(1)
        ->and($user->pageVisits()->sole()->last_opened_at->greaterThan($first))->toBeTrue();
});

test('the recents widget lists the current user visits newest first with a resource link', function () {
    $project = makeProject(['name' => 'Progetto recente']);
    $workspace = $project->workspace;
    $user = User::factory()->create();
    $workspace->users()->attach($user);

    $this->actingAs($user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($workspace);

    PageVisit::create([
        'user_id' => $user->id,
        'visitable_type' => $project->getMorphClass(),
        'visitable_id' => $project->id,
        'last_opened_at' => now(),
    ]);
    PageVisit::create([
        'user_id' => User::factory()->create()->id,
        'visitable_type' => $project->getMorphClass(),
        'visitable_id' => $project->id,
        'last_opened_at' => now(),
    ]);

    Livewire::test(RecentsWidget::class)
        ->assertCanSeeTableRecords($user->pageVisits()->get())
        ->assertSee('Progetto recente')
        ->assertSee(ProjectResource::getUrl('edit', ['record' => $project], tenant: $workspace));
});
