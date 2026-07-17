<?php

use App\Models\Tag;
use App\Models\Workspace;

test('a tag belongs to a workspace', function () {
    $workspace = Workspace::factory()->create();
    $tag = Tag::factory()->for($workspace)->create();

    expect($tag->workspace->is($workspace))->toBeTrue();
});
