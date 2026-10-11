<?php

namespace App\Policies;

class SermonPolicy extends ChurchContentPolicy
{
    protected string $permission = 'content.manage';
}
