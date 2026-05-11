<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Design\DeleteCustomViewpoint as BaseDeleteCustomViewpoint;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a custom architecture viewpoint. Refuses while any architecture view still uses it.')]
class DeleteArchitectureViewpoint extends BaseDeleteCustomViewpoint {}
