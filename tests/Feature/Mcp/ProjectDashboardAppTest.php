<?php

use App\Mcp\Resources\ProjectDashboardApp;

it('omits ui.domain so Claude can assign its own sandbox origin', function () {
    config()->set('app.url', 'https://growth-2ifox3yq.on-forge.com');

    $meta = (new ProjectDashboardApp)->resolvedAppMeta();

    expect($meta)->not->toHaveKey('domain');
});
