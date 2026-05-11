<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Plan\DeleteProjectPlan as BaseDeleteProjectPlan;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete the delivery plan for a project. Milestones, work items, and roles are not deleted.')]
class DeletePlan extends BaseDeleteProjectPlan {}
