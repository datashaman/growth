<?php

use App\Mcp\Resources\ProjectDashboardApp;

it('omits ui.domain so Claude can assign its own sandbox origin', function () {
    config()->set('app.url', 'https://growth.datashaman.com');

    $meta = (new ProjectDashboardApp)->resolvedAppMeta();

    expect($meta)->not->toHaveKey('domain');
});
